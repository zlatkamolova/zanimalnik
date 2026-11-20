<?php

namespace App\Daily;

use App\Entity\Joke;
use App\Repository\JokeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Random\Randomizer;
use Symfony\Component\Lock\LockFactory;

/**
 * Joke / anecdote / meme of the day: one for the whole site, chosen on the first visit of the day.
 *
 * Rules
 *  - candidates: the active entries;
 *  - random among those not shown in the last 30 days (or never shown);
 *  - when there are fewer than that, the one shown longest ago — so nothing repeats
 *    before everything else has had its day;
 *  - the choice is remembered in Joke::lastShownOn and stays the same all day;
 *    if the admin switches off or deletes today's entry, another one is chosen.
 */
final class DailyJokeService
{
    public const TIMEZONE = 'Europe/Sofia';
    public const NO_REPEAT_DAYS = 30;

    private readonly Randomizer $randomizer;

    public function __construct(
        private readonly JokeRepository $jokes,
        private readonly EntityManagerInterface $em,
        private readonly LockFactory $lockFactory,
        private readonly ClockInterface $clock,
        ?Randomizer $randomizer = null,
    ) {
        $this->randomizer = $randomizer ?? new Randomizer();
    }

    public function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone(self::TIMEZONE))->setTime(0, 0);
    }

    /** The entry of $date (today by default), choosing it first if needed; null when there is nothing to show. */
    public function forDate(?\DateTimeImmutable $date = null): ?Joke
    {
        $date = ($date ?? $this->today())->setTime(0, 0);

        if ($joke = $this->jokes->findShownOn($date)) {
            return $joke;
        }

        // two visitors at 00:00 must not choose two different jokes
        $lock = $this->lockFactory->createLock('daily-joke-'.$date->format('Y-m-d'), 30);
        $lock->acquire(true);
        try {
            return $this->jokes->findShownOn($date) ?? $this->choose($date);
        } finally {
            $lock->release();
        }
    }

    private function choose(\DateTimeImmutable $date): ?Joke
    {
        $candidates = $this->jokes->findActive();
        if (!$candidates) {
            return null;
        }

        $limit = $date->modify(\sprintf('-%d days', self::NO_REPEAT_DAYS));
        $fresh = array_values(array_filter(
            $candidates,
            fn (Joke $j) => null === $j->getLastShownOn() || $j->getLastShownOn() <= $limit,
        ));

        if ($fresh) {
            $joke = $fresh[$this->randomizer->getInt(0, \count($fresh) - 1)];
        } else {
            usort($candidates, fn (Joke $a, Joke $b) => [$a->getLastShownOn(), $a->getId()] <=> [$b->getLastShownOn(), $b->getId()]);
            $joke = $candidates[0];
        }

        $joke->setLastShownOn($date);
        $this->em->flush();

        return $joke;
    }
}
