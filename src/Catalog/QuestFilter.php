<?php

namespace App\Catalog;

/** Filter criteria for the public quest list. Empty values mean "all". */
final readonly class QuestFilter
{
    public function __construct(
        public ?string $path = null,
        public ?int $difficulty = null,
        public ?string $tag = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return !$this->path && !$this->difficulty && !$this->tag;
    }
}
