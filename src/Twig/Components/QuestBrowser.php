<?php

namespace App\Twig\Components;

use App\Catalog\QuestCatalog;
use App\Catalog\QuestFilter;
use App\Entity\Path;
use App\Entity\Quest;
use App\Entity\Tag;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Metadata\UrlMapping;

/**
 * Quest list with filters by path, difficulty and tag. Re-renders without a page reload;
 * the filters are mirrored in the URL (?pateka=…&trudnost=…&tag=…) so a filtered list can be shared.
 * Without JavaScript the same markup works as a normal GET form.
 */
#[AsLiveComponent]
final class QuestBrowser
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: new UrlMapping(as: 'pateka'))]
    public string $path = '';

    #[LiveProp(writable: true, url: new UrlMapping(as: 'trudnost'))]
    public int $difficulty = 0;

    #[LiveProp(writable: true, url: new UrlMapping(as: 'tag'))]
    public string $tag = '';

    private ?array $quests = null;

    public function __construct(private readonly QuestCatalog $catalog)
    {
    }

    /** @return list<Quest> */
    public function getQuests(): array
    {
        return $this->quests ??= $this->catalog->find(new QuestFilter(
            $this->path ?: null,
            $this->difficulty ?: null,
            $this->tag ?: null,
        ));
    }

    /** @return list<Path> */
    public function getPaths(): array
    {
        return $this->catalog->paths();
    }

    /** @return list<Tag> */
    public function getTags(): array
    {
        return $this->catalog->usedTags();
    }

    /** @return array<int, string> */
    public function getDifficulties(): array
    {
        return Quest::DIFFICULTY_LABELS;
    }

    public function hasFilters(): bool
    {
        return '' !== $this->path || 0 !== $this->difficulty || '' !== $this->tag;
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->path = '';
        $this->difficulty = 0;
        $this->tag = '';
    }
}
