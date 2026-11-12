<?php

namespace App\Entity;

use App\Repository\SettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Simple key → value store for editable site texts (e.g. the "За сайта" page). */
#[ORM\Entity(repositoryClass: SettingRepository::class)]
#[ORM\Table(name: 'settings')]
#[ORM\HasLifecycleCallbacks]
class Setting implements \Stringable
{
    public const ABOUT_TITLE = 'about.title';
    public const ABOUT_BODY = 'about.body';

    /** Human labels for the admin; keys ending in ".body" are edited as rich text. */
    public const LABELS = [
        self::ABOUT_TITLE => '„За сайта“ — заглавие',
        self::ABOUT_BODY => '„За сайта“ — текст',
    ];

    #[ORM\Id]
    #[ORM\Column(name: 'setting_key', length: 100)]
    private string $key;

    #[ORM\Column(type: Types::TEXT)]
    private string $value = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(string $key, string $value = '')
    {
        $this->key = $key;
        $this->value = $value;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return self::LABELS[$this->key] ?? $this->key;
    }

    public function isRichText(): bool
    {
        return str_ends_with($this->key, '.body');
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = (string) $value;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
