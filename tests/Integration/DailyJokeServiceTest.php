<?php

namespace App\Tests\Integration;

use App\Daily\DailyJokeService;
use App\Entity\Joke;
use App\Tests\Support\DatabaseTestCase;

final class DailyJokeServiceTest extends DatabaseTestCase
{
    private function service(): DailyJokeService
    {
        return self::getContainer()->get(DailyJokeService::class);
    }

    private function day(int $offset): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-12-01', new \DateTimeZone('Europe/Sofia')))->modify("+$offset days");
    }

    private function createJokes(int $count, bool $active = true): void
    {
        for ($i = 1; $i <= $count; ++$i) {
            $this->em->persist((new Joke())->setText("Шега $i")->setIsActive($active));
        }
        $this->em->flush();
    }

    public function testNothingToShowWithoutActiveJokes(): void
    {
        self::assertNull($this->service()->forDate($this->day(0)));

        $this->createJokes(2, active: false);
        self::assertNull($this->service()->forDate($this->day(0)));
    }

    public function testSameJokeAllDay(): void
    {
        $this->createJokes(10);

        $first = $this->service()->forDate($this->day(0));
        self::assertNotNull($first);
        for ($i = 0; $i < 5; ++$i) {
            $this->em->clear();
            self::assertSame($first->getId(), $this->service()->forDate($this->day(0))->getId());
        }
    }

    public function testNoRepeatWithin30DaysWhenThereAreEnough(): void
    {
        $this->createJokes(40);

        $byDay = [];
        for ($d = 0; $d < 90; ++$d) {
            $byDay[$d] = $this->service()->forDate($this->day($d))->getId();
            for ($prev = max(0, $d - 29); $prev < $d; ++$prev) {
                self::assertNotSame($byDay[$d], $byDay[$prev], "Joke repeated on day $d after day $prev (< 30 days).");
            }
        }
    }

    public function testFewJokesTakeTurnsBeforeAnyRepeats(): void
    {
        $this->createJokes(3);

        $ids = [];
        for ($d = 0; $d < 9; ++$d) {
            $ids[] = $this->service()->forDate($this->day($d))->getId();
        }

        foreach (array_chunk($ids, 3) as $round) {
            self::assertCount(3, array_unique($round), 'Every joke is shown once per round: '.implode(', ', $ids));
        }
    }

    public function testAnotherOneIsChosenWhenTodaysJokeIsSwitchedOff(): void
    {
        $this->createJokes(2);

        $first = $this->service()->forDate($this->day(0));
        $first->setIsActive(false);
        $this->em->flush();

        $second = $this->service()->forDate($this->day(0));
        self::assertNotNull($second);
        self::assertNotSame($first->getId(), $second->getId());
    }
}
