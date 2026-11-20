<?php

namespace App\Repository;

use App\Entity\Joke;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Joke> */
class JokeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Joke::class);
    }

    /** The active joke already chosen for $date, if any. */
    public function findShownOn(\DateTimeImmutable $date): ?Joke
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.isActive = true')
            ->andWhere('j.lastShownOn = :date')
            ->setParameter('date', $date->setTime(0, 0), 'date_immutable')
            ->orderBy('j.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Joke> */
    public function findActive(): array
    {
        return $this->findBy(['isActive' => true], ['id' => 'ASC']);
    }
}
