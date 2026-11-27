<?php

namespace App\Entity;

use App\Repository\WallCommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A public comment on a wall, written through the form or through POST /api/bot-speak.
 * Created only by App\Wall\WallCommentService. Replies are kept one level deep:
 * the parent is always the first comment of the thread.
 */
#[ORM\Entity(repositoryClass: WallCommentRepository::class)]
#[ORM\Table(name: 'wall_comments')]
#[ORM\Index(name: 'idx_wall_comments_wall', columns: ['wall_id', 'is_hidden', 'created_at'])]
#[ORM\Index(name: 'idx_wall_comments_sender', columns: ['ip_hash', 'created_at'])]
class WallComment
{
    public const VIA_FORM = 'form';
    public const VIA_API = 'api';

    /** Names of the site itself; only a signed-in administrator may use them (in the form). */
    public const RESERVED_NAMES = ['admin', 'админ', 'administrator', 'администратор', 'занималник', 'zanimalnik'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'wall_id', nullable: false, onDelete: 'CASCADE')]
    private Wall $wall;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'CASCADE')]
    private ?WallComment $parent;

    #[ORM\Column(length: 40)]
    private string $name;

    /** Plain text; line breaks are kept. */
    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 60)]
    private string $os;

    #[ORM\Column(length: 10)]
    private string $via;

    /** SHA-256 of the sender's address and the secret key; the address itself is never stored. */
    #[ORM\Column(length: 64, options: ['fixed' => true])]
    private string $ipHash;

    /** SHA-256 of the normalised text, to recognise the same comment sent twice. */
    #[ORM\Column(length: 64, options: ['fixed' => true])]
    private string $messageHash;

    /** Kept for moderation, never shown on the site. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent;

    #[ORM\Column(options: ['default' => false])]
    private bool $isHidden = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Wall $wall,
        ?WallComment $parent,
        string $name,
        string $message,
        string $os,
        string $via,
        string $ipHash,
        string $messageHash,
        ?string $userAgent,
        \DateTimeImmutable $createdAt,
    ) {
        $this->wall = $wall;
        $this->parent = $parent;
        $this->name = $name;
        $this->message = $message;
        $this->os = $os;
        $this->via = $via;
        $this->ipHash = $ipHash;
        $this->messageHash = $messageHash;
        $this->userAgent = $userAgent;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWall(): Wall
    {
        return $this->wall;
    }

    public function getParent(): ?WallComment
    {
        return $this->parent;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public static function isReservedName(string $name): bool
    {
        return \in_array(mb_strtolower($name), self::RESERVED_NAMES, true);
    }

    /** Written under one of the reserved names, so by the administrator (or created with the wall). */
    public function isFromAdmin(): bool
    {
        return self::isReservedName($this->name);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getOs(): string
    {
        return $this->os;
    }

    public function getVia(): string
    {
        return $this->via;
    }

    public function isViaApi(): bool
    {
        return self::VIA_API === $this->via;
    }

    public function getIpHash(): string
    {
        return $this->ipHash;
    }

    /** Short form for the admin list: the same sender has the same code. */
    public function getSender(): string
    {
        return substr($this->ipHash, 0, 8);
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    public function setIsHidden(bool $isHidden): static
    {
        $this->isHidden = $isHidden;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
