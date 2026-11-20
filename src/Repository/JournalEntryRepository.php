<?php

namespace App\Repository;

use App\Entity\JournalEntry;
use App\Entity\Quest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Every query here is scoped to one user: a journal is never read across users.
 *
 * @extends ServiceEntityRepository<JournalEntry>
 */
class JournalEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalEntry::class);
    }

    /**
     * Newest first.
     *
     * @return list<JournalEntry>
     */
    public function findPage(User $user, int $page, int $perPage): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('q', 'p')
            ->leftJoin('e.quest', 'q')
            ->leftJoin('q.path', 'p')
            ->andWhere('e.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.completedOn', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setFirstResult(max(0, $page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countFor(User $user, ?Quest $quest = null): int
    {
        return $this->count($quest ? ['user' => $user, 'quest' => $quest] : ['user' => $user]);
    }

    public function countDistinctQuests(User $user): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(DISTINCT e.quest)')
            ->andWhere('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLastFor(User $user, Quest $quest): ?JournalEntry
    {
        return $this->findOneBy(['user' => $user, 'quest' => $quest], ['completedOn' => 'DESC', 'id' => 'DESC']);
    }
}
