<?php

namespace App\Tests\Integration;

use App\Repository\DailyChallengeRepository;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DailyPickCommandTest extends DatabaseTestCase
{
    private function tester(): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find('app:daily-pick'));
    }

    public function testFillsOneChallengePerPathAndDoesNotDuplicateWithin14Days(): void
    {
        foreach (['Взор', 'Слово', 'Оракул', 'Кривите пътеки'] as $i => $name) {
            $path = $this->createPath($name, $i * 10);
            for ($q = 1; $q <= 16; ++$q) {
                $this->createQuest($path, "$name $q");
            }
        }
        $this->em->flush();

        $tester = $this->tester();
        $tester->execute(['--date' => '2026-12-01', '--days' => '14'], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('', trim($tester->getDisplay()), 'silent from cron, so cPanel sends no e-mail');

        $this->em->clear();
        $all = self::getContainer()->get(DailyChallengeRepository::class)->findAll();
        self::assertCount(56, $all, '4 paths × 14 days');

        $perPath = [];
        foreach ($all as $c) {
            $perPath[$c->getPath()->getName()][] = $c->getQuest()->getId();
        }
        foreach ($perPath as $name => $questIds) {
            self::assertCount(14, $questIds);
            self::assertCount(14, array_unique($questIds), "$name: a quest was repeated within 14 days.");
        }

        // running again changes nothing
        $tester->execute(['--date' => '2026-12-01', '--days' => '14'], ['interactive' => false]);
        $this->em->clear();
        self::assertSame(56, self::getContainer()->get(DailyChallengeRepository::class)->count([]));
    }

    public function testReportsWhenRunByHand(): void
    {
        $path = $this->createPath('Взор');
        $this->createQuest($path, 'Един');
        $this->em->flush();

        $tester = $this->tester();
        $tester->execute(['--date' => '2026-12-01'], ['interactive' => true]);

        self::assertStringContainsString('Готово: 1 предизвикателства.', $tester->getDisplay());
    }

    public function testRejectsInvalidDate(): void
    {
        $tester = $this->tester();
        $tester->execute(['--date' => '2026-02-30'], ['interactive' => false]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertStringContainsString('Невалидна дата', $tester->getDisplay());
    }

    public function testWarnsWhenThereAreNoQuests(): void
    {
        $this->createPath('Празна');
        $this->em->flush();

        $tester = $this->tester();
        $tester->execute([], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Няма публикувани куестове', $tester->getDisplay());
    }
}
