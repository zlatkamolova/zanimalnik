<?php

namespace App\Repository;

use App\Entity\Path;
use App\Entity\Quest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Quest> */
class QuestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quest::class);
    }

    /** Base query for everything visitors may see. */
    public function publishedQueryBuilder(string $alias = 'q'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->andWhere("$alias.isPublished = true")
            ->andWhere("$alias.publishedAt <= :now")
            ->setParameter('now', new \DateTimeImmutable());
    }

    /** Restricts a query to quests whose main OR additional path is $path. */
    public function filterByPath(QueryBuilder $qb, Path $path, string $alias = 'q'): QueryBuilder
    {
        return $qb
            ->andWhere("$alias.path = :path OR :path MEMBER OF $alias.additionalPaths")
            ->setParameter('path', $path);
    }

    public function findOnePublishedBySlug(string $slug): ?Quest
    {
        return $this->publishedQueryBuilder()
            ->andWhere('q.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Quest> */
    public function findLatestPublished(int $limit = 6): array
    {
        return $this->publishedQueryBuilder()
            ->orderBy('q.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Quest> */
    public function findPublishedByPath(Path $path): array
    {
        return $this->filterByPath($this->publishedQueryBuilder(), $path)
            ->orderBy('q.difficulty', 'ASC')
            ->addOrderBy('q.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Published quests that may be picked as challenge of the day for $path. @return list<Quest> */
    public function findDailyCandidates(Path $path): array
    {
        return $this->filterByPath($this->publishedQueryBuilder(), $path)
            ->andWhere('q.isDailyEligible = true')
            ->orderBy('q.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
