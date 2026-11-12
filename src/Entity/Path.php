<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\PathRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** A path (пътека): Взор, Слово, Оракул, Кривите пътеки … managed by the admin. */
#[ORM\Entity(repositoryClass: PathRepository::class)]
#[ORM\Table(name: 'paths')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'Вече има пътека с този адрес (slug).')]
class Path implements SluggableInterface, \Stringable
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Името е задължително.')]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\Column(length: 120, unique: true)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Адресът може да съдържа само малки латински букви, цифри и тирета.')]
    #[Assert\Length(max: 120)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    /** Short sign or icon name, e.g. "eye", "quill" — drawn as a seal on the site. */
    #[ORM\Column(length: 32)]
    #[Assert\NotBlank(message: 'Символът е задължителен.')]
    #[Assert\Length(max: 32)]
    private string $symbol = '';

    #[ORM\Column(length: 7)]
    #[Assert\NotBlank(message: 'Цветът е задължителен.')]
    #[Assert\CssColor(formats: Assert\CssColor::HEX_LONG, message: 'Цветът трябва да е във формат #RRGGBB.')]
    private string $color = '#8a3b12';

    #[ORM\Column]
    private int $sortOrder = 0;

    public function __toString(): string
    {
        return $this->name;
    }

    public function getSlugSource(): string
    {
        return $this->getName();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = (string) $description;

        return $this;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): static
    {
        $this->symbol = $symbol;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = strtolower($color);

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }
}
