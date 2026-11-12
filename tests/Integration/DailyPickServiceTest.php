<?php

namespace App\Tests\Integration;

use App\Daily\DailyPickService;
use App\Entity\DailyChallenge;
use App\Repository\DailyChallengeRepository;
use App\Tests\Support\DatabaseTestCase;

final class DailyPickServiceTest extends DatabaseTestCase
{
    private function service(): DailyPickService
    {
        return self::getContainer()->get(DailyPickService::class);
    }

    private function day(int $offset): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-12-01', new \DateTimeZone('Europe/Sofia')))->modify("+$offset days");
    }

    public function testOneQuestPerPathPerDayAndNoRepeatWithin14Days(): void
    {
        $a = $this->createPath('Взор', 10);
        $b = $this->createPath('Слово', 20);
        for ($i = 1; $i <= 20; ++$i) {
            $this->createQuest($a, "Взор $i");
            $this->createQuest($b, "Слово $i");
        }
        $this->em->flush();

        $history = [];
        for ($d = 0; $d < 60; ++$d) {
            $challenges = $this->service()->ensureForDate($this->day($d));
            self::assertCount(2, $challenges, "Day $d must have one challenge per path.");
            foreach ($challenges as $c) {
                self::assertContains($c->getPath(), $c->getQuest()->getAllPaths());
                $history[$c->getPath()->getName()][$d] = $c->getQuest()->getId();
            }
        }

        foreach ($history as $pathName => $byDay) {
            foreach ($byDay as $day => $questId) {
                for ($prev = max(0, $day - 13); $prev < $day; ++$prev) {
                    self::assertNotSame($questId, $byDay[$prev], "$pathName: quest repeated on day $day after day $prev (< 14 days).");
                }
            }
        }
    }

    public function testRunningTwiceCreatesNothingNew(): void
    {
        $path = $this->createPath('Оракул');
        $this->createQuest($path, 'Първи');
        $this->createQuest($path, 'Втори');
        $this->em->flush();

        self::assertCount(1, $this->service()->fillMissing($this->day(0)));
        self::assertCount(0, $this->service()->fillMissing($this->day(0)));
        self::assertCount(1, $this->service()->ensureForDate($this->day(0)));
    }

    public function testManualChoiceIsNeverOverwritten(): void
    {
        $path = $this->createPath('Слово');
        $pinned = $this->createQuest($path, 'Избран на ръка');
        $this->createQuest($path, 'Друг');
        $this->em->persist((new DailyChallenge())->setDate($this->day(0))->setPath($path)->setQuest($pinned)->setIsManual(true));
        $this->em->flush();

        $result = $this->service()->ensureForDate($this->day(0));

        self::assertCount(1, $result);
        self::assertSame('Избран на ръка', $result[0]->getQuest()->getTitle());
        self::assertTrue($result[0]->isManual());
    }

    public function testQuestPinnedInTheFutureIsNotUsedJustBefore(): void
    {
        $path = $this->createPath('Взор');
        $pinned = $this->createQuest($path, 'Закован за след 5 дни');
        for ($i = 1; $i <= 5; ++$i) {
            $this->createQuest($path, "Друг $i");
        }
        $this->em->persist((new DailyChallenge())->setDate($this->day(5))->setPath($path)->setQuest($pinned)->setIsManual(true));
        $this->em->flush();

        for ($d = 0; $d < 5; ++$d) {
            $challenge = $this->service()->ensureForDate($this->day($d))[0];
            self::assertNotSame('Закован за след 5 дни', $challenge->getQuest()->getTitle(), "Day $d");
        }
    }

    public function testWithTooFewQuestsTheLeastRecentlyUsedIsRepeated(): void
    {
        $path = $this->createPath('Кривите пътеки');
        $this->createQuest($path, 'А');
        $this->createQuest($path, 'Б');
        $this->createQuest($path, 'В');
        $this->em->flush();

        $titles = [];
        for ($d = 0; $d < 9; ++$d) {
            $titles[] = $this->service()->ensureForDate($this->day($d))[0]->getQuest()->getTitle();
        }

        // still a challenge every day, and the three quests take turns (no back-to-back repeats)
        self::assertCount(9, $titles);
        for ($d = 1; $d < 9; ++$d) {
            self::assertNotSame($titles[$d - 1], $titles[$d]);
        }
        self::assertSame(array_slice($titles, 0, 3), array_slice($titles, 3, 3));
    }

    public function testDraftsAndNonEligibleQuestsAreNeverPicked(): void
    {
        $path = $this->createPath('Взор');
        $this->createQuest($path, 'Чернова', published: false);
        $this->createQuest($path, 'Не за деня', eligible: false);
        $this->em->flush();

        self::assertSame([], $this->service()->ensureForDate($this->day(0)));

        $this->createQuest($path, 'Подходящ');
        $this->em->flush();
        self::assertSame('Подходящ', $this->service()->ensureForDate($this->day(0))[0]->getQuest()->getTitle());
    }

    public function testCrossroadsQuestIsNotUsedForTwoPathsOnTheSameDay(): void
    {
        $a = $this->createPath('Взор', 10);
        $b = $this->createPath('Кривите пътеки', 20);
        $shared = $this->createQuest($b, 'На кръстопът');
        $shared->addAdditionalPath($a);
        for ($i = 1; $i <= 15; ++$i) {
            $this->createQuest($a, "Взор $i");
            $this->createQuest($b, "Криви $i");
        }
        $this->em->flush();

        for ($d = 0; $d < 30; ++$d) {
            $ids = array_map(fn ($c) => $c->getQuest()->getId(), $this->service()->ensureForDate($this->day($d)));
            self::assertCount(2, $ids);
            self::assertCount(2, array_unique($ids), "Day $d: same quest on two paths.");
        }
    }

    public function testFourteenDayRuleWinsOverSameDayRule(): void
    {
        // "Криви" has a single quest, shared with "Взор": it must be used every day for "Криви",
        // and "Взор" must still never repeat within 14 days.
        $a = $this->createPath('Взор', 10);
        $b = $this->createPath('Криви', 20);
        $shared = $this->createQuest($b, 'На кръстопът');
        $shared->addAdditionalPath($a);
        for ($i = 1; $i <= 14; ++$i) {
            $this->createQuest($a, "Взор $i");
        }
        $this->em->flush();

        $vzor = [];
        for ($d = 0; $d < 20; ++$d) {
            foreach ($this->service()->ensureForDate($this->day($d)) as $c) {
                if ('Криви' === $c->getPath()->getName()) {
                    self::assertSame('На кръстопът', $c->getQuest()->getTitle());
                } else {
                    $vzor[$d] = $c->getQuest()->getId();
                }
            }
        }
        foreach ($vzor as $d => $id) {
            for ($p = max(0, $d - 13); $p < $d; ++$p) {
                self::assertNotSame($id, $vzor[$p], "Взор repeated on day $d");
            }
        }
    }

    public function testChallengesAreStoredOncePerDateAndPath(): void
    {
        $path = $this->createPath('Слово');
        $this->createQuest($path, 'Един');
        $this->em->flush();

        $this->service()->ensureForDate($this->day(0));
        $this->service()->ensureForDate($this->day(0));
        $this->em->clear();

        self::assertSame(1, self::getContainer()->get(DailyChallengeRepository::class)->count([]));
    }
}
