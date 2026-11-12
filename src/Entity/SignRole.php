<?php

namespace App\Entity;

use App\Repository\SignRoleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** A role the visitor can pick as the basis for the sign of the day (Пазителят, Скитникът …). */
#[ORM\Entity(repositoryClass: SignRoleRepository::class)]
#[ORM\Table(name: 'sign_roles')]
#[UniqueEntity(fields: ['slug'], message: 'Вече има роля с този адрес (slug).')]
class SignRole implements SluggableInterface, \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank(message: 'Името е задължително.')]
    #[Assert\Length(max: 60)]
    private string $name = '';

    #[ORM\Column(length: 80, unique: true)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Адресът може да съдържа само малки латински букви, цифри и тирета.')]
    private ?string $slug = null;

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
