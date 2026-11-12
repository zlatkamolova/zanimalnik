<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\QuestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuestRepository::class)]
#[ORM\Table(name: 'quests')]
#[ORM\Index(name: 'idx_quests_published', columns: ['is_published', 'published_at'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'Вече има куест с този адрес (slug).')]
class Quest implements SluggableInterface, \Stringable
{
    use TimestampableTrait;

    public const DIFFICULTY_LABELS = [1 => 'Лесен', 2 => 'Среден', 3 => 'Труден'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Main path. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'path_id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Изберете основна пътека.')]
    private ?Path $path = null;

    /** Extra paths for quests at a crossroads (pivot table quest_path). */
    #[ORM\ManyToMany(targetEntity: Path::class)]
    #[ORM\JoinTable(name: 'quest_path')]
    #[ORM\JoinColumn(name: 'quest_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'path_id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $additionalPaths;

    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'quest_tag')]
    #[ORM\JoinColumn(name: 'quest_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'tag_id', onDelete: 'CASCADE')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $tags;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank(message: 'Заглавието е задължително.')]
    #[Assert\Length(max: 160)]
    private string $title = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Адресът може да съдържа само малки латински букви, цифри и тирета.')]
    #[Assert\Length(max: 180)]
    private ?string $slug = null;

    /** 1–2 sentences for cards and meta description. */
    #[ORM\Column(length: 400)]
    #[Assert\NotBlank(message: 'Краткото описание е задължително.')]
    #[Assert\Length(max: 400, maxMessage: 'Краткото описание е твърде дълго (най-много {{ limit }} знака).')]
    private string $summary = '';

    /** Rich text (HTML): rules and description. */
    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 3, notInRangeMessage: 'Трудността трябва да е между 1 и 3.')]
    private int $difficulty = 1;

    /** Free text, e.g. "един ден". */
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Продължителността е задължителна.')]
    #[Assert\Length(max: 100)]
    private string $duration = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $materials = null;

    /** File name inside uploads/quests/pdf. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pdfPath = null;

    /** File name inside uploads/quests/covers. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImagePath = null;

    #[ORM\Column]
    private bool $isPublished = false;

    #[ORM\Column]
    private bool $isDailyEligible = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    public function __construct()
    {
        $this->additionalPaths = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title;
    }

    /** First time a quest is published, remember when. */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function stampPublishedAt(): void
    {
        if ($this->isPublished && null === $this->publishedAt) {
            $this->publishedAt = new \DateTimeImmutable();
        }
    }

    /** Is the quest visible to visitors right now? */
    public function isVisible(?\DateTimeImmutable $now = null): bool
    {
        return $this->isPublished && null !== $this->publishedAt && $this->publishedAt <= ($now ?? new \DateTimeImmutable());
    }

    /** Main path first, then the additional ones (without duplicates). */
    public function getAllPaths(): array
    {
        $paths = $this->path ? [$this->path] : [];
        foreach ($this->additionalPaths as $extra) {
            if ($extra !== $this->path) {
                $paths[] = $extra;
            }
        }

        return $paths;
    }

    public function getDifficultyLabel(): string
    {
        return self::DIFFICULTY_LABELS[$this->difficulty] ?? '';
    }

    public function getSlugSource(): string
    {
        return $this->getTitle();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /** @return Collection<int, Path> */
    public function getAdditionalPaths(): Collection
    {
        return $this->additionalPaths;
    }

    public function addAdditionalPath(Path $path): static
    {
        if (!$this->additionalPaths->contains($path)) {
            $this->additionalPaths->add($path);
        }

        return $this;
    }

    public function removeAdditionalPath(Path $path): static
    {
        $this->additionalPaths->removeElement($path);

        return $this;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = (string) $body;

        return $this;
    }

    public function getDifficulty(): int
    {
        return $this->difficulty;
    }

    public function setDifficulty(int $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getDuration(): string
    {
        return $this->duration;
    }

    public function setDuration(string $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getMaterials(): ?string
    {
        return $this->materials;
    }

    public function setMaterials(?string $materials): static
    {
        $this->materials = $materials;

        return $this;
    }

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(?string $pdfPath): static
    {
        $this->pdfPath = $pdfPath;

        return $this;
    }

    public function getCoverImagePath(): ?string
    {
        return $this->coverImagePath;
    }

    public function setCoverImagePath(?string $coverImagePath): static
    {
        $this->coverImagePath = $coverImagePath;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function isDailyEligible(): bool
    {
        return $this->isDailyEligible;
    }

    public function setIsDailyEligible(bool $isDailyEligible): static
    {
        $this->isDailyEligible = $isDailyEligible;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }
}
