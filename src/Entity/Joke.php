<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\JokeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A joke, an anecdote or a meme. One of them is shown on the home page every day
 * (see App\Daily\DailyJokeService). Text, image or both.
 */
#[ORM\Entity(repositoryClass: JokeRepository::class)]
#[ORM\Table(name: 'jokes')]
#[ORM\Index(name: 'idx_jokes_shown', columns: ['last_shown_on'])]
#[ORM\HasLifecycleCallbacks]
class Joke implements \Stringable
{
    use TimestampableTrait;

    public const TYPE_JOKE = 'joke';
    public const TYPE_ANECDOTE = 'anecdote';
    public const TYPE_MEME = 'meme';

    public const TYPE_LABELS = [
        self::TYPE_JOKE => 'Шега',
        self::TYPE_ANECDOTE => 'Виц',
        self::TYPE_MEME => 'Меме',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::TYPE_JOKE, self::TYPE_ANECDOTE, self::TYPE_MEME], message: 'Изберете вид.')]
    private string $type = self::TYPE_JOKE;

    /** Plain text; line breaks are kept on the site. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: 'Текстът е твърде дълъг (най-много {{ limit }} знака).')]
    private ?string $text = null;

    /** File name inside uploads/jokes. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    /** What the image shows, for visitors who can't see it. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $imageAlt = null;

    #[ORM\Column]
    private bool $isActive = true;

    /** The last day it was "of the day"; null = never shown. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastShownOn = null;

    public function __toString(): string
    {
        return $this->getTypeLabel().': '.mb_strimwidth($this->getPreview(), 0, 60, '…');
    }

    #[Assert\Callback]
    public function validateContent(ExecutionContextInterface $context): void
    {
        if (!$this->text && !$this->imagePath) {
            $context->buildViolation('Въведете текст или качете изображение.')->atPath('text')->addViolation();
        }
        if ($this->imagePath && !$this->imageAlt) {
            $context->buildViolation('Опишете с няколко думи какво има на изображението.')->atPath('imageAlt')->addViolation();
        }
    }

    public function getTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? '';
    }

    /** One line for lists in the admin. */
    public function getPreview(): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $this->text ?: (string) $this->imageAlt));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $text = trim((string) $text);
        $this->text = '' === $text ? null : $text;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getImageAlt(): ?string
    {
        return $this->imageAlt;
    }

    public function setImageAlt(?string $imageAlt): static
    {
        $imageAlt = trim((string) $imageAlt);
        $this->imageAlt = '' === $imageAlt ? null : $imageAlt;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getLastShownOn(): ?\DateTimeImmutable
    {
        return $this->lastShownOn;
    }

    public function setLastShownOn(?\DateTimeImmutable $lastShownOn): static
    {
        $this->lastShownOn = $lastShownOn?->setTime(0, 0);

        return $this;
    }
}
