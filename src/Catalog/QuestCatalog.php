<?php

namespace App\Catalog;

use App\Entity\Path;
use App\Entity\Quest;
use App\Entity\Tag;
use App\Repository\PathRepository;
use App\Repository\QuestRepository;
use App\Repository\TagRepository;

/**
 * What visitors may see: published quests, filtered and ordered.
 * Controllers and Live Components call this instead of building queries.
 */
final class QuestCatalog
{
    public function __construct(
        private readonly QuestRepository $quests,
        private readonly PathRepository $paths,
        private readonly TagRepository $tags,
    ) {
    }

    /** @return list<Quest> */
    public function find(QuestFilter $filter): array
    {
        $qb = $this->quests->publishedQueryBuilder()
            ->addSelect('p')
            ->innerJoin('q.path', 'p')
            ->orderBy('q.publishedAt', 'DESC')
            ->addOrderBy('q.id', 'DESC');

        if ($filter->path && ($path = $this->paths->findOneBy(['slug' => $filter->path]))) {
            $this->quests->filterByPath($qb, $path);
        }
        if ($filter->difficulty && isset(Quest::DIFFICULTY_LABELS[$filter->difficulty])) {
            $qb->andWhere('q.difficulty = :difficulty')->setParameter('difficulty', $filter->difficulty);
        }
        if ($filter->tag && ($tag = $this->tags->findOneBy(['slug' => $filter->tag]))) {
            $qb->andWhere(':tag MEMBER OF q.tags')->setParameter('tag', $tag);
        }

        return $qb->getQuery()->getResult();
    }

    public function findBySlug(string $slug): ?Quest
    {
        return $this->quests->findOnePublishedBySlug($slug);
    }

    /** @return list<Quest> */
    public function latest(int $limit = 6): array
    {
        return $this->quests->findLatestPublished($limit);
    }

    /** @return list<Quest> */
    public function forPath(Path $path): array
    {
        return $this->quests->findPublishedByPath($path);
    }

    /** @return list<Path> */
    public function paths(): array
    {
        return $this->paths->findAllOrdered();
    }

    public function pathBySlug(string $slug): ?Path
    {
        return $this->paths->findOneBy(['slug' => $slug]);
    }

    /** Tags that appear on at least one visible quest. @return list<Tag> */
    public function usedTags(): array
    {
        return $this->tags->findUsedByPublishedQuests();
    }
}
