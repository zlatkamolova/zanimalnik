<?php

namespace App\Repository;

use App\Entity\Wall;
use App\Entity\WallComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * "Visible" everywhere below = not hidden, and not a reply to a hidden comment.
 *
 * @extends ServiceEntityRepository<WallComment>
 */
class WallCommentRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 100;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WallComment::class);
    }

    public function findVisible(Wall $wall, int $id): ?WallComment
    {
        return $this->visible($wall)->andWhere('c.id = :id')->setParameter('id', $id)->getQuery()->getOneOrNullResult();
    }

    public function countVisible(Wall $wall): int
    {
        return (int) $this->visible($wall)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
    }

    /**
     * The newest $limit visible comments, newest first.
     *
     * @return list<WallComment>
     */
    public function findLatestVisible(Wall $wall, int $limit): array
    {
        return $this->visible($wall)->orderBy('c.id', 'DESC')->setMaxResults($limit)->getQuery()->getResult();
    }

    /**
     * Visible comments as pages of whole threads, oldest first. A page is closed once it has
     * PER_PAGE comments, so a thread is never split between two pages.
     *
     * @return list<list<array{comment: int, replies: list<int>}>> pages → threads → comment ids
     */
    public function threadPages(Wall $wall): array
    {
        $rows = $this->visible($wall)
            ->select('c.id AS id', 'IDENTITY(c.parent) AS parent')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $threads = [];
        foreach ($rows as $row) {
            if (null === $row['parent']) {
                $threads[(int) $row['id']] = ['comment' => (int) $row['id'], 'replies' => []];
            } elseif (isset($threads[(int) $row['parent']])) {
                $threads[(int) $row['parent']]['replies'][] = (int) $row['id'];
            }
        }

        $pages = [];
        $page = [];
        $size = 0;
        foreach ($threads as $thread) {
            $page[] = $thread;
            $size += 1 + \count($thread['replies']);
            if ($size >= self::PER_PAGE) {
                $pages[] = $page;
                $page = [];
                $size = 0;
            }
        }
        if ($page) {
            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, WallComment> by id
     */
    public function findByIds(array $ids): array
    {
        $comments = [];
        foreach ($ids ? $this->findBy(['id' => $ids]) : [] as $comment) {
            $comments[$comment->getId()] = $comment;
        }

        return $comments;
    }

    public function isDuplicate(Wall $wall, string $ipHash, string $messageHash): bool
    {
        return null !== $this->findOneBy(['wall' => $wall, 'ipHash' => $ipHash, 'messageHash' => $messageHash]);
    }

    /**
     * Creation times of the sender's comments since $since, oldest first (hidden ones count too).
     *
     * @return list<\DateTimeImmutable>
     */
    public function timesBySender(string $ipHash, \DateTimeImmutable $since): array
    {
        return $this->times($this->createQueryBuilder('c')->andWhere('c.ipHash = :hash')->setParameter('hash', $ipHash), $since);
    }

    /**
     * Creation times of all comments on the wall since $since, oldest first (hidden ones count too).
     *
     * @return list<\DateTimeImmutable>
     */
    public function timesOnWall(Wall $wall, \DateTimeImmutable $since): array
    {
        return $this->times($this->createQueryBuilder('c')->andWhere('c.wall = :wall')->setParameter('wall', $wall), $since);
    }

    /** Hides every comment of the sender on this wall; returns how many were changed. */
    public function hideAllBySender(Wall $wall, string $ipHash): int
    {
        return (int) $this->createQueryBuilder('c')
            ->update()
            ->set('c.isHidden', 'true')
            ->andWhere('c.wall = :wall')
            ->andWhere('c.ipHash = :hash')
            ->andWhere('c.isHidden = false')
            ->setParameter('wall', $wall)
            ->setParameter('hash', $ipHash)
            ->getQuery()
            ->execute();
    }

    private function visible(Wall $wall): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.parent', 'p')
            ->andWhere('c.wall = :wall')
            ->andWhere('c.isHidden = false')
            ->andWhere('p.id IS NULL OR p.isHidden = false')
            ->setParameter('wall', $wall);
    }

    /** @return list<\DateTimeImmutable> */
    private function times(QueryBuilder $qb, \DateTimeImmutable $since): array
    {
        $rows = $qb
            ->select('c.createdAt AS createdAt')
            ->andWhere('c.createdAt > :since')
            ->setParameter('since', $since, 'datetime_immutable')
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'createdAt');
    }
}
