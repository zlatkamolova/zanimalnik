<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Visitors who registered (ROLE_USER: their own journal) and administrators (additionally ROLE_ADMIN).
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Вече има потребител с този имейл.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, \Stringable
{
    use TimestampableTrait;

    public const ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Имейлът е задължителен.')]
    #[Assert\Email(message: 'Невалиден имейл адрес.')]
    private string $email = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    /** Hashed password. */
    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Името е твърде дълго (най-много {{ limit }} знака).')]
    private ?string $displayName = null;

    /** sha256 of the "forgotten password" token; the token itself only travels in the e-mail. */
    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $resetTokenHash = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    /** @var Collection<int, JournalEntry> */
    #[ORM\OneToMany(targetEntity: JournalEntry::class, mappedBy: 'user', cascade: ['remove'], fetch: 'EXTRA_LAZY')]
    private Collection $journalEntries;

    public function __construct()
    {
        $this->journalEntries = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->displayName ?: $this->email;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique($roles));

        return $this;
    }

    public function isAdmin(): bool
    {
        return \in_array(self::ROLE_ADMIN, $this->roles, true);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $displayName = trim((string) $displayName);
        $this->displayName = '' === $displayName ? null : $displayName;

        return $this;
    }

    public function getResetTokenHash(): ?string
    {
        return $this->resetTokenHash;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpiresAt;
    }

    /** Both null = no reset in progress. */
    public function setResetToken(?string $hash, ?\DateTimeImmutable $expiresAt): static
    {
        $this->resetTokenHash = $hash;
        $this->resetTokenExpiresAt = $expiresAt;

        return $this;
    }

    /** @return Collection<int, JournalEntry> */
    public function getJournalEntries(): Collection
    {
        return $this->journalEntries;
    }

    #[\Deprecated(message: 'Required by UserInterface until Symfony 8; no plain-text secrets are stored on the user.')]
    public function eraseCredentials(): void
    {
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        // store only a hash of the password hash in the session
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
