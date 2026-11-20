<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\JournalEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One line of a visitor's private journal: "I did this quest on that day" plus a note.
 * The same quest may be logged any number of times. Only the owner ever sees it.
 */
#[ORM\Entity(repositoryClass: JournalEntryRepository::class)]
#[ORM\Table(name: 'journal_entries')]
#[ORM\Index(name: 'idx_journal_user_date', columns: ['user_id', 'completed_on'])]
#[ORM\HasLifecycleCallbacks]
class JournalEntry
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'journalEntries')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** Null when the admin deleted the quest later: the entry and its note stay, with the title below. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'quest_id', nullable: true, onDelete: 'SET NULL')]
    private ?Quest $quest = null;

    /** The quest title at the time of writing. */
    #[ORM\Column(length: 160)]
    private string $questTitle = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Изберете дата.')]
    #[Assert\LessThanOrEqual('today', message: 'Датата не може да е в бъдещето.')]
    private ?\DateTimeImmutable $completedOn = null;

    /** Plain text; line breaks are kept. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000, maxMessage: 'Бележката е твърде дълга (най-много {{ limit }} знака).')]
    private ?string $note = null;

    public function __construct(?User $user = null, ?Quest $quest = null)
    {
        $this->user = $user;
        $this->completedOn = new \DateTimeImmutable('today');
        if ($quest) {
            $this->setQuest($quest);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getQuest(): ?Quest
    {
        return $this->quest;
    }

    public function setQuest(Quest $quest): static
    {
        $this->quest = $quest;
        $this->questTitle = $quest->getTitle();

        return $this;
    }

    public function getQuestTitle(): string
    {
        return $this->quest?->getTitle() ?? $this->questTitle;
    }

    public function getCompletedOn(): ?\DateTimeImmutable
    {
        return $this->completedOn;
    }

    public function setCompletedOn(?\DateTimeImmutable $completedOn): static
    {
        $this->completedOn = $completedOn?->setTime(0, 0);

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $note = trim((string) $note);
        $this->note = '' === $note ? null : $note;

        return $this;
    }
}
