<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Tag> */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /** Tags used by at least one published quest, alphabetically. @return list<Tag> */
    public function findUsedByPublishedQuests(): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('App\Entity\Quest', 'q', 'WITH', 't MEMBER OF q.tags')
            ->andWhere('q.isPublished = true')
            ->andWhere('q.publishedAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->groupBy('t.id')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
