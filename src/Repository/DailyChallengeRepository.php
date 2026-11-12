<?php

namespace App\Repository;

use App\Entity\DailyChallenge;
use App\Entity\Path;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DailyChallenge> */
class DailyChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyChallenge::class);
    }

    /** @return list<DailyChallenge> */
    public function findForDate(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('d')
            ->addSelect('p', 'q')
            ->innerJoin('d.path', 'p')
            ->innerJoin('d.quest', 'q')
            ->andWhere('d.date = :date')
            ->setParameter('date', $date->setTime(0, 0), 'date_immutable')
            ->orderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForDateAndPath(\DateTimeImmutable $date, Path $path): ?DailyChallenge
    {
        return $this->findOneBy(['date' => $date->setTime(0, 0), 'path' => $path]);
    }

    /**
     * Quest ids used for $path in [$from, $to) — to avoid repeats.
     *
     * @return list<int>
     */
    public function findQuestIdsUsedBetween(Path $path, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return array_map('intval', $this->createQueryBuilder('d')
            ->select('IDENTITY(d.quest)')
            ->andWhere('d.path = :path')
            ->andWhere('d.date >= :from AND d.date < :to')
            ->setParameter('path', $path)
            ->setParameter('from', $from->setTime(0, 0), 'date_immutable')
            ->setParameter('to', $to->setTime(0, 0), 'date_immutable')
            ->getQuery()
            ->getSingleColumnResult());
    }

    /**
     * Last date (before $before) on which each quest was the challenge of $path.
     *
     * @return array<int, string> questId => 'Y-m-d'
     */
    public function findLastUseDates(Path $path, \DateTimeImmutable $before): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('IDENTITY(d.quest) AS quest', 'MAX(d.date) AS last')
            ->andWhere('d.path = :path')
            ->andWhere('d.date < :before')
            ->setParameter('path', $path)
            ->setParameter('before', $before->setTime(0, 0), 'date_immutable')
            ->groupBy('d.quest')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['quest']] = $row['last'] instanceof \DateTimeInterface ? $row['last']->format('Y-m-d') : (string) $row['last'];
        }

        return $result;
    }
}
