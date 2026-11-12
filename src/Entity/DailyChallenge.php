<?php

namespace App\Entity;

use App\Repository\DailyChallengeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** One quest per path per day. Filled by app:daily-pick; the admin may override a day. */
#[ORM\Entity(repositoryClass: DailyChallengeRepository::class)]
#[ORM\Table(name: 'daily_challenges')]
#[ORM\UniqueConstraint(name: 'uniq_daily_date_path', columns: ['date', 'path_id'])]
#[UniqueEntity(fields: ['date', 'path'], message: 'За тази дата вече има предизвикателство по тази пътека.')]
class DailyChallenge implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Датата е задължителна.')]
    private ?\DateTimeImmutable $date = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'path_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Изберете пътека.')]
    private ?Path $path = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'quest_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Изберете куест.')]
    private ?Quest $quest = null;

    /** True when the admin chose the quest by hand (the automatic pick must not overwrite it). */
    #[ORM\Column]
    private bool $isManual = false;

    public function __toString(): string
    {
        return \sprintf('%s · %s', $this->date?->format('d.m.Y') ?? '', (string) $this->path);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date?->setTime(0, 0);

        return $this;
    }

    public function getPath(): ?Path
    {
        return $this->path;
    }

    public function setPath(?Path $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getQuest(): ?Quest
    {
        return $this->quest;
    }

    public function setQuest(?Quest $quest): static
    {
        $this->quest = $quest;

        return $this;
    }

    public function isManual(): bool
    {
        return $this->isManual;
    }

    public function setIsManual(bool $isManual): static
    {
        $this->isManual = $isManual;

        return $this;
    }
}
