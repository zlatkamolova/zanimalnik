<?php

namespace App\Entity;

/** Entities whose slug is generated from a title when left empty. */
interface SluggableInterface
{
    public function getSlug(): ?string;

    public function setSlug(?string $slug): static;

    /** The text the slug is generated from. */
    public function getSlugSource(): string;
}
