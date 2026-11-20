<?php

namespace App\Tests\Functional;

use App\Entity\JournalEntry;
use App\Entity\Quest;
use App\Entity\User;
use App\Tests\Support\WebDatabaseTestCase;

/** The private journal: finished quests with notes, the same quest any number of times. */
final class JournalTest extends WebDatabaseTestCase
{
    private const ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    private User $mila;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadFixtures();
        $this->mila = $this->createUser('mila@example.com', []);
    }

    private function quest(string $slug): Quest
    {
        return $this->em->getRepository(Quest::class)->findOneBy(['slug' => $slug]);
    }

    private function entry(User $user, string $slug, string $date, ?string $note = null): JournalEntry
    {
        $entry = (new JournalEntry($this->em->getReference(User::class, $user->getId()), $this->quest($slug)))
            ->setCompletedOn(new \DateTimeImmutable($date))
            ->setNote($note);
        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    private function addFromQuestPage(string $slug, array $values): void
    {
        $crawler = $this->client->request('GET', '/kuest/'.$slug);
        $this->client->submit($crawler->filter('form[name="journal_entry"]')->form($values), [], self::ORIGIN);
    }

    public function testAnonymousVisitorSeesAnInvitationInsteadOfTheForm(): void
    {
        $crawler = $this->client->request('GET', '/kuest/nekazanoto');

        self::assertSelectorNotExists('form[name="journal_entry"]');
        self::assertSame('/vhod?kam=/kuest/nekazanoto', $crawler->filter('.quest-journal a.btn')->attr('href'));
        self::assertSame([], $this->client->getResponse()->headers->getCookies());

        $this->client->request('POST', '/dnevnik/zapis/nekazanoto', [], [], self::ORIGIN);
        self::assertResponseRedirects('/vhod');
    }

    public function testQuestCanBeWrittenDownSeveralTimesWithNotes(): void
    {
        $this->client->loginUser($this->mila);

        $this->addFromQuestPage('nekazanoto', ['journal_entry[completedOn]' => '2026-11-01', 'journal_entry[note]' => "Първи опит.\nТрудно беше."]);
        self::assertResponseRedirects('/dnevnik');
        $this->addFromQuestPage('nekazanoto', ['journal_entry[completedOn]' => '2026-11-15', 'journal_entry[note]' => 'Втори опит.']);
        $this->addFromQuestPage('sedem-zhalti-neshta', ['journal_entry[completedOn]' => '2026-11-10']);

        $crawler = $this->client->followRedirect();
        self::assertSame('„Седем жълти неща“ е записан в дневника.', $crawler->filter('.flash')->last()->text());
        self::assertSelectorTextContains('.page-lead', '3 записа · 2 различни куеста');
        self::assertSame(
            ['Неказаното', 'Седем жълти неща', 'Неказаното'],
            $crawler->filter('.journal-title')->each(fn ($n) => trim($n->text())),
            'newest first',
        );
        self::assertSelectorTextContains('.journal-entry:last-child .journal-note', 'Първи опит.');
        self::assertCount(1, $crawler->filter('.journal-entry:last-child .journal-note br'), 'line breaks are kept');
        self::assertSelectorTextContains('.journal-entry:first-child .journal-date', '15 ноември 2026');

        // the quest page remembers
        $this->client->request('GET', '/kuest/nekazanoto');
        self::assertSelectorTextContains('.quest-journal', 'има 2 записа за този куест');
        self::assertSelectorTextContains('.quest-journal', '15 ноември 2026');
    }

    public function testFutureDateIsRejectedInBulgarian(): void
    {
        $this->client->loginUser($this->mila);

        $this->addFromQuestPage('nekazanoto', ['journal_entry[completedOn]' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d')]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Датата не може да е в бъдещето.');
        self::assertSame(0, $this->em->getRepository(JournalEntry::class)->count([]));
    }

    public function testEditAndDeleteAnEntry(): void
    {
        $entry = $this->entry($this->mila, 'nekazanoto', '2026-11-01', 'Стара бележка');
        $this->client->loginUser($this->mila);

        $crawler = $this->client->request('GET', '/dnevnik/'.$entry->getId().'/redaktsiya');
        self::assertSelectorTextContains('h1', 'Неказаното');
        $this->client->submit($crawler->filter('form[name="journal_entry"]')->form(['journal_entry[completedOn]' => '2026-11-02', 'journal_entry[note]' => 'Нова бележка']), [], self::ORIGIN);
        self::assertResponseRedirects('/dnevnik');

        $this->em->clear();
        $saved = $this->em->find(JournalEntry::class, $entry->getId());
        self::assertSame('Нова бележка', $saved->getNote());
        self::assertSame('2026-11-02', $saved->getCompletedOn()->format('Y-m-d'));

        $crawler = $this->client->request('GET', '/dnevnik/'.$entry->getId().'/redaktsiya');
        $this->client->submit($crawler->selectButton('Изтрий записа')->form(), [], self::ORIGIN);
        self::assertResponseRedirects('/dnevnik');
        $this->em->clear();
        self::assertNull($this->em->find(JournalEntry::class, $entry->getId()));
    }

    public function testJournalIsPrivate(): void
    {
        $other = $this->createUser('drug@example.com', []);
        $theirs = $this->entry($other, 'nekazanoto', '2026-11-03', 'Чужда тайна бележка');
        $this->entry($this->mila, 'nekazanoto', '2026-11-04', 'Моя бележка');
        $this->client->loginUser($this->mila);

        $this->client->request('GET', '/dnevnik');
        self::assertSelectorTextContains('.journal', 'Моя бележка');
        self::assertStringNotContainsString('Чужда тайна бележка', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/dnevnik/'.$theirs->getId().'/redaktsiya');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/dnevnik/'.$theirs->getId().'/redaktsiya', ['journal_entry' => ['completedOn' => '2026-11-05', 'note' => 'пробив']], [], self::ORIGIN);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/dnevnik/'.$theirs->getId().'/iztrivane', [], [], self::ORIGIN);
        self::assertResponseStatusCodeSame(404);

        $this->em->clear();
        self::assertSame('Чужда тайна бележка', $this->em->find(JournalEntry::class, $theirs->getId())->getNote());

        // the admin list shows how many entries a user has, never the notes
        $this->client->loginUser($this->createUser('admin@example.com', [User::ROLE_ADMIN]));
        $this->client->request('GET', '/admin/user');
        self::assertStringNotContainsString('тайна бележка', (string) $this->client->getResponse()->getContent());
    }

    public function testEntrySurvivesWhenTheQuestIsDeleted(): void
    {
        $entry = $this->entry($this->mila, 'nekazanoto', '2026-11-01', 'Помня го');
        $this->em->remove($this->quest('nekazanoto'));
        $this->em->flush();
        $this->em->clear();
        $this->client->loginUser($this->mila);

        $crawler = $this->client->request('GET', '/dnevnik');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.journal-title', 'Неказаното');
        self::assertCount(0, $crawler->filter('.journal-title a'), 'no link to a quest that is gone');
        self::assertSelectorTextContains('.journal-note', 'Помня го');
        self::assertNotNull($this->em->find(JournalEntry::class, $entry->getId()));
    }

    public function testLongJournalIsPaged(): void
    {
        for ($i = 1; $i <= 25; ++$i) {
            $this->em->persist((new JournalEntry($this->em->getReference(User::class, $this->mila->getId()), $this->quest('nekazanoto')))
                ->setCompletedOn(new \DateTimeImmutable("2026-10-$i")));
        }
        $this->em->flush();
        $this->client->loginUser($this->mila);

        $crawler = $this->client->request('GET', '/dnevnik');
        self::assertCount(20, $crawler->filter('.journal-entry'));
        self::assertSelectorTextContains('.journal-pages', 'Страница 1 от 2');

        $crawler = $this->client->request('GET', '/dnevnik?stranitsa=2');
        self::assertCount(5, $crawler->filter('.journal-entry'));

        $crawler = $this->client->request('GET', '/dnevnik?stranitsa=99');
        self::assertCount(5, $crawler->filter('.journal-entry'), 'a page past the end shows the last one');
    }
}
