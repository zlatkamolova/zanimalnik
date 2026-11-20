<?php

namespace App\Tests\Functional;

use App\Entity\JournalEntry;
use App\Entity\Quest;
use App\Entity\User;
use App\Tests\Support\WebDatabaseTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Visitor accounts: registration, login and logout, forgotten password, profile, deleting the account. */
final class AccountTest extends WebDatabaseTestCase
{
    private const ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    private function register(array $values = []): void
    {
        $crawler = $this->client->request('GET', '/registratsiya');
        $form = $crawler->filter('form[name="registration"]')->form($values + [
            'registration[email]' => 'Mila@Example.com ',
            'registration[displayName]' => 'Мила',
            'registration[plainPassword]' => 'tainstvena-parola',
            'registration[agree]' => true,
        ]);
        $this->client->submit($form, [], self::ORIGIN);
    }

    private function login(string $email, string $password, array $extra = []): void
    {
        $crawler = $this->client->request('GET', '/vhod');
        $form = $crawler->filter('form')->form(['_username' => $email, '_password' => $password] + $extra);
        $this->client->submit($form, [], self::ORIGIN);
    }

    private function user(string $email): ?User
    {
        $this->em->clear();

        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function testRegistrationCreatesAnOrdinaryUserAndOpensTheJournal(): void
    {
        $this->register();
        self::assertResponseRedirects('/dnevnik');

        $user = $this->user('mila@example.com');
        self::assertNotNull($user);
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame('Мила', $user->getDisplayName());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'tainstvena-parola'));

        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Моят дневник');
        self::assertSelectorTextContains('.flash', 'Профилът е създаден.');
        self::assertSelectorTextContains('.eyebrow', 'Мила');
        self::assertSelectorTextContains('.empty-note', 'Дневникът още е празен.');
        self::assertSelectorTextContains('#site-nav', 'Дневник');

        // an ordinary user never reaches the admin
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testRegistrationErrorsAreInBulgarian(): void
    {
        $this->createUser('mila@example.com', []);

        $this->register(['registration[plainPassword]' => 'kratka', 'registration[agree]' => false]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Вече има потребител с този имейл.');
        self::assertSelectorTextContains('body', 'Паролата трябва да е поне 8 знака.');
        self::assertSelectorTextContains('body', 'трябва да приемеш правилата за поверителност');
        self::assertSame(1, $this->em->getRepository(User::class)->count([]));
    }

    public function testBotThatFillsTheHiddenFieldGetsNoAccount(): void
    {
        $this->register(['registration[website]' => 'http://spam.example']);

        self::assertResponseRedirects('/vhod');
        self::assertNull($this->user('mila@example.com'));
    }

    public function testJournalAndProfileNeedALoginAndReturnThereAfterwards(): void
    {
        $this->createUser('mila@example.com', [], 'tainstvena-parola');

        $this->client->request('GET', '/dnevnik');
        self::assertResponseRedirects('/vhod');
        $this->client->request('GET', '/profil');
        self::assertResponseRedirects('/vhod');

        $this->client->followRedirect();
        $form = $this->client->getCrawler()->filter('form')->form(['_username' => 'mila@example.com', '_password' => 'tainstvena-parola']);
        $this->client->submit($form, [], self::ORIGIN);
        self::assertResponseRedirects('http://localhost/profil');
    }

    public function testLoginWrongPasswordStaysOnTheSiteLoginInBulgarian(): void
    {
        $this->createUser('mila@example.com', [], 'tainstvena-parola');

        $this->login('mila@example.com', 'greshna-parola');
        self::assertResponseRedirects('/vhod');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.form-error', 'Грешен имейл или парола.');
        self::assertSelectorTextContains('#site-nav', 'Вход');
    }

    public function testLoginGoesBackToTheQuestAndNeverToAnotherSite(): void
    {
        $this->loadFixtures();
        $this->createUser('mila@example.com', [], 'tainstvena-parola');

        $crawler = $this->client->request('GET', '/vhod?kam=/kuest/nekazanoto');
        $this->client->submit($crawler->filter('form')->form(['_username' => 'mila@example.com', '_password' => 'tainstvena-parola']), [], self::ORIGIN);
        self::assertResponseRedirects('/kuest/nekazanoto');

        $this->client->request('GET', '/izhod');
        self::assertResponseRedirects('/');

        foreach (['https://evil.example/', '//evil.example', '/\\evil.example'] as $bad) {
            $crawler = $this->client->request('GET', '/vhod?kam='.rawurlencode($bad));
            self::assertSame('/dnevnik', $crawler->filter('input[name="_target_path"]')->attr('value'), $bad);
        }
    }

    public function testRememberMeCookieOnlyWhenAsked(): void
    {
        $this->createUser('mila@example.com', [], 'tainstvena-parola');

        $this->login('mila@example.com', 'tainstvena-parola');
        self::assertNull($this->client->getCookieJar()->get('REMEMBERME'));
        $this->client->request('GET', '/izhod');

        $this->login('mila@example.com', 'tainstvena-parola', ['_remember_me' => true]);
        self::assertNotNull($this->client->getCookieJar()->get('REMEMBERME'));
    }

    public function testForgottenPasswordSendsALinkThatWorksOnce(): void
    {
        $this->createUser('mila@example.com', [], 'stara-parola-123');

        $crawler = $this->client->request('GET', '/zabravena-parola');
        $this->client->submit($crawler->filter('form[name="form"]')->form(['form[email]' => 'MILA@example.com']), [], self::ORIGIN);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.flash', 'Ако има профил с този имейл');
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'to', 'mila@example.com');
        self::assertEmailAddressContains($email, 'from', 'no-reply@zanimalnik.com');
        self::assertEmailSubjectContains($email, 'Нова парола');
        self::assertSame(1, preg_match('#http://localhost(/nova-parola/[a-f0-9]{64})#', $email->getTextBody(), $m));
        self::assertEmailHtmlBodyContains($email, $m[1]);

        // only a hash of the token is stored
        self::assertStringNotContainsString((string) $this->user('mila@example.com')->getResetTokenHash(), $m[1]);

        $crawler = $this->client->request('GET', $m[1]);
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[name="form"]')->form(['form[plainPassword]' => 'chisto-nova-parola']), [], self::ORIGIN);
        self::assertResponseRedirects('/dnevnik');

        $user = $this->user('mila@example.com');
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, 'chisto-nova-parola'));
        self::assertNull($user->getResetTokenHash());

        // the link is spent
        $this->client->request('GET', $m[1]);
        self::assertResponseStatusCodeSame(410);
        self::assertSelectorTextContains('.form-error', 'Връзката е невалидна или е изтекла.');
    }

    public function testForgottenPasswordSaysTheSameForUnknownEmailAndExpiredLinksFail(): void
    {
        $crawler = $this->client->request('GET', '/zabravena-parola');
        $this->client->submit($crawler->filter('form[name="form"]')->form(['form[email]' => 'nyama@example.com']), [], self::ORIGIN);
        self::assertSelectorTextContains('.flash', 'Ако има профил с този имейл');
        self::assertEmailCount(0);

        $user = $this->createUser('mila@example.com', []);
        $token = str_repeat('ab', 32);
        $user->setResetToken(hash('sha256', $token), new \DateTimeImmutable('-1 minute'));
        $this->em->flush();

        $this->client->request('GET', '/nova-parola/'.$token);
        self::assertResponseStatusCodeSame(410);
    }

    public function testProfileChangesNameAndPasswordAndKeepsTheVisitorLoggedIn(): void
    {
        $this->client->loginUser($this->createUser('mila@example.com', [], 'stara-parola-123'));

        $crawler = $this->client->request('GET', '/profil');
        $this->client->submit($crawler->filter('form[name="name"]')->form(['name[displayName]' => 'Скитницата']), [], self::ORIGIN);
        self::assertResponseRedirects('/profil');
        self::assertSame('Скитницата', $this->user('mila@example.com')->getDisplayName());

        $crawler = $this->client->request('GET', '/profil');
        $this->client->submit($crawler->filter('form[name="password"]')->form(['password[current]' => 'greshna', 'password[new]' => 'chisto-nova-parola']), [], self::ORIGIN);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Текущата парола не е вярна.');

        $crawler = $this->client->request('GET', '/profil');
        $this->client->submit($crawler->filter('form[name="password"]')->form(['password[current]' => 'stara-parola-123', 'password[new]' => 'chisto-nova-parola']), [], self::ORIGIN);
        self::assertResponseRedirects('/profil');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.flash', 'Паролата е сменена.');
    }

    public function testDeletingTheAccountRemovesTheJournalToo(): void
    {
        $this->loadFixtures();
        $user = $this->createUser('mila@example.com', [], 'tainstvena-parola');
        $quest = $this->em->getRepository(Quest::class)->findOneBy(['slug' => 'nekazanoto']);
        $this->em->persist((new JournalEntry($user, $quest))->setNote('Лична бележка'));
        $this->em->flush();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/profil');
        $this->client->submit($crawler->filter('form[name="delete"]')->form(['delete[current]' => 'tainstvena-parola']), [], self::ORIGIN);

        self::assertResponseRedirects('/');
        self::assertNull($this->user('mila@example.com'));
        self::assertSame(0, $this->em->getRepository(JournalEntry::class)->count([]));

        $this->client->request('GET', '/dnevnik');
        self::assertResponseRedirects('/vhod');
    }

    public function testAdministratorCannotDeleteTheirOwnAccountFromTheProfile(): void
    {
        $this->client->loginUser($this->createUser('admin@example.com', [User::ROLE_ADMIN]));

        $this->client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="delete"]');
    }

    public function testAdminSeesUsersButDeletesOnlyOrdinaryOnes(): void
    {
        $this->client->loginUser($this->createUser('admin@example.com', [User::ROLE_ADMIN]));
        $visitor = $this->createUser('mila@example.com', []);

        $crawler = $this->client->request('GET', '/admin/user');
        self::assertSelectorTextContains('table', 'mila@example.com');
        self::assertCount(1, $crawler->filter('tr[data-id="'.$visitor->getId().'"] .action-delete'));
        self::assertCount(1, $crawler->filter('table .action-delete'), 'no delete button for the administrator');
    }
}
