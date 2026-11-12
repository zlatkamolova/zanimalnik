<?php

namespace App\Daily;

use App\Entity\DailyChallenge;
use App\Entity\Path;
use App\Entity\Quest;
use App\Repository\DailyChallengeRepository;
use App\Repository\PathRepository;
use App\Repository\QuestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Random\Randomizer;
use Symfony\Component\Lock\LockFactory;

/**
 * Challenges of the day: one quest per path per day.
 *
 * Rules
 *  - candidates: published quests of the path (main or additional) marked "is_daily_eligible";
 *  - a quest is not repeated for the same path within 14 days (before or after: days the admin
 *    pinned in advance count too), as long as there are enough candidates;
 *  - otherwise the candidate used longest ago (or never) wins;
 *  - the same quest is not used for two paths on the same day, unless only that would keep the 14-day rule;
 *  - days/paths that already have an entry (automatic or pinned by the admin) are never touched.
 *
 * Runs from cron (app:daily-pick) and, as a fallback, on the first request of the day.
 */
final class DailyPickService
{
    public const TIMEZONE = 'Europe/Sofia';
    public const NO_REPEAT_DAYS = 14;

    private readonly Randomizer $randomizer;

    public function __construct(
        private readonly PathRepository $paths,
        private readonly QuestRepository $quests,
        private readonly DailyChallengeRepository $challenges,
        private readonly EntityManagerInterface $em,
        private readonly LockFactory $lockFactory,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        ?Randomizer $randomizer = null,
    ) {
        $this->randomizer = $randomizer ?? new Randomizer();
    }

    public function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone(self::TIMEZONE))->setTime(0, 0);
    }

    /**
     * Returns the challenges of $date (ordered by path), creating the missing ones first.
     *
     * @return list<DailyChallenge>
     */
    public function ensureForDate(?\DateTimeImmutable $date = null): array
    {
        $date = ($date ?? $this->today())->setTime(0, 0);
        $existing = $this->challenges->findForDate($date);

        if (\count($existing) < \count($this->paths->findAllOrdered())) {
            // two visitors at 00:00 must not both create the same day
            $lock = $this->lockFactory->createLock('daily-pick-'.$date->format('Y-m-d'), 30);
            $lock->acquire(true);
            try {
                $this->fillMissing($date);
            } finally {
                $lock->release();
            }
            $existing = $this->challenges->findForDate($date);
        }

        return $existing;
    }

    /**
     * Creates the missing entries of one day.
     *
     * @return list<DailyChallenge> the newly created entries
     */
    public function fillMissing(\DateTimeImmutable $date): array
    {
        $date = $date->setTime(0, 0);
        $created = [];
        $usedToday = [];

        foreach ($this->challenges->findForDate($date) as $challenge) {
            $usedToday[$challenge->getQuest()->getId()] = true;
        }

        foreach ($this->paths->findAllOrdered() as $path) {
            if ($this->challenges->findOneForDateAndPath($date, $path)) {
                continue;
            }

            $quest = $this->choose($path, $date, $usedToday);
            if (!$quest) {
                $this->logger->info('No daily challenge candidates for path {path}.', ['path' => $path->getSlug()]);
                continue;
            }

            $challenge = (new DailyChallenge())->setDate($date)->setPath($path)->setQuest($quest)->setIsManual(false);
            $this->em->persist($challenge);
            $usedToday[$quest->getId()] = true;
            $created[] = $challenge;
        }

        if ($created) {
            $this->em->flush();
        }

        return $created;
    }

    /** @param array<int, true> $usedToday quest ids already chosen for other paths on $date */
    private function choose(Path $path, \DateTimeImmutable $date, array $usedToday): ?Quest
    {
        $candidates = $this->quests->findDailyCandidates($path);
        if (!$candidates) {
            return null;
        }

        $window = $this->challenges->findQuestIdsUsedBetween(
            $path,
            $date->modify(\sprintf('-%d days', self::NO_REPEAT_DAYS - 1)),
            $date->modify(\sprintf('+%d days', self::NO_REPEAT_DAYS)),
        );
        $isFresh = fn (Quest $q) => !\in_array($q->getId(), $window, true);
        $notToday = fn (Quest $q) => !isset($usedToday[$q->getId()]);

        // Priority: the 14-day rule (required) beats "not on two paths today" (nice to have).
        foreach ([
            array_filter($candidates, fn (Quest $q) => $isFresh($q) && $notToday($q)),
            array_filter($candidates, $isFresh),
        ] as $pool) {
            if ($pool) {
                $pool = array_values($pool);

                return $pool[$this->randomizer->getInt(0, \count($pool) - 1)];
            }
        }

        // Not enough quests for 14 days: the one used longest ago (or never) for this path.
        $lastUse = $this->challenges->findLastUseDates($path, $date);
        $pool = array_values(array_filter($candidates, $notToday)) ?: $candidates;
        usort($pool, fn (Quest $a, Quest $b) => [$lastUse[$a->getId()] ?? '0000-00-00', $a->getId()] <=> [$lastUse[$b->getId()] ?? '0000-00-00', $b->getId()]);

        return $pool[0];
    }
}
