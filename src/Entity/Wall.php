<?php

namespace App\Entity;

use App\Repository\WallRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A page with public comments that looks like a single blog post (see docs/stena-zadanie.md).
 * The text of the post lives in templates/wall/posts/{slug}.html.twig.
 */
#[ORM\Entity(repositoryClass: WallRepository::class)]
#[ORM\Table(name: 'walls')]
class Wall implements \Stringable
{
    public const TIMEZONE = 'Europe/Sofia';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $slug;

    #[ORM\Column(length: 160)]
    private string $title;

    /** Part of the address (/2026/11/27/slug/); never changes after the first day. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $publishedAt;

    #[ORM\Column(options: ['default' => true])]
    private bool $isOpen = true;

    public function __construct(string $slug, string $title, \DateTimeImmutable $publishedAt)
    {
        $this->slug = $slug;
        $this->title = $title;
        $this->publishedAt = $publishedAt;
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function setIsOpen(bool $isOpen): static
    {
        $this->isOpen = $isOpen;

        return $this;
    }

    /**
     * Route parameters of the page: the publishing date in Sofia time plus the slug.
     *
     * @return array{year: string, month: string, day: string, slug: string}
     */
    public function getRouteParams(): array
    {
        $date = $this->publishedAt->setTimezone(new \DateTimeZone(self::TIMEZONE));

        return ['year' => $date->format('Y'), 'month' => $date->format('m'), 'day' => $date->format('d'), 'slug' => $this->slug];
    }
}
