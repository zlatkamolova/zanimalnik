<?php

namespace App\Tests\Functional;

use App\Entity\Path;
use App\Entity\Quest;
use App\Entity\User;
use App\Tests\Support\WebDatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class AdminAccessTest extends WebDatabaseTestCase
{
    /** Every admin page (GET). Entity ids are filled in at runtime. */
    public static function adminPages(): iterable
    {
        yield 'табло' => ['/admin'];
        yield 'куестове' => ['/admin/quest'];
        yield 'нов куест' => ['/admin/quest/new'];
        yield 'редакция на куест' => ['/admin/quest/{quest}/edit'];
        yield 'пътеки' => ['/admin/path'];
        yield 'подредба на пътеките' => ['/admin/path/sort'];
        yield 'тагове' => ['/admin/tag'];
        yield 'роли' => ['/admin/sign-role'];
        yield 'шеги' => ['/admin/joke'];
        yield 'нова шега' => ['/admin/joke/new'];
        yield 'текстове' => ['/admin/setting'];
        yield 'потребители' => ['/admin/user'];
        yield 'предизвикателства' => ['/admin/daily-challenges'];
    }

    private function url(string $pattern): string
    {
        $quest = $this->em->getRepository(Quest::class)->findOneBy(['isPublished' => true]);

        return str_replace('{quest}', (string) $quest?->getId(), $pattern);
    }

    #[DataProvider('adminPages')]
    public function testAnonymousVisitorIsSentToLogin(string $page): void
    {
        $this->loadFixtures();
        $this->client->request('GET', $this->url($page));

        self::assertResponseRedirects('/admin/login');
    }

    #[DataProvider('adminPages')]
    public function testRegularUserIsForbidden(string $page): void
    {
        // a future visitor account (ROLE_USER only) must never reach the admin
        $this->loadFixtures();
        $this->client->loginUser($this->createUser('visitor@example.com', []));
        $this->client->request('GET', $this->url($page));

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('adminPages')]
    public function testAdminCanOpenEveryPage(string $page): void
    {
        $this->loadFixtures();
        $this->client->loginUser($this->createUser('admin@example.com', [User::ROLE_ADMIN]));
        $this->client->request('GET', $this->url($page));

        self::assertResponseIsSuccessful();
    }

    public function testWriteEndpointsAreProtected(): void
    {
        $this->loadFixtures();
        $path = $this->em->getRepository(Path::class)->findOneBy([]);

        // anonymous POSTs are redirected to the login, nothing changes
        $this->client->request('POST', '/admin/path/sort/save', [], [], ['CONTENT_TYPE' => 'application/json'], '{"ids":[]}');
        self::assertResponseRedirects('/admin/login');
        $this->client->request('POST', '/admin/daily-challenges/set', ['date' => date('Y-m-d'), 'path' => $path->getId()]);
        self::assertResponseRedirects('/admin/login');

        // a logged-in admin without the CSRF token is refused as well
        $this->client->loginUser($this->createUser('admin@example.com', [User::ROLE_ADMIN]));
        $this->client->request('POST', '/admin/path/sort/save', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => 'wrong'], '{"ids":[]}');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLoginWithWrongPasswordShowsBulgarianError(): void
    {
        $this->createUser('admin@example.com', [User::ROLE_ADMIN], 'correct-horse-battery');

        $crawler = $this->client->request('GET', '/admin/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('label[for="username"]', 'Имейл');

        $form = $crawler->filter('form')->form(['_username' => 'admin@example.com', '_password' => 'wrong-password']);
        $this->client->submit($form, [], ['HTTP_ORIGIN' => 'http://localhost']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.login-wrapper', 'Грешен имейл или парола.');
        self::assertSelectorNotExists('a[href="/admin/logout"]');
    }

    public function testAdminCanLogInAndOut(): void
    {
        $this->createUser('admin@example.com', [User::ROLE_ADMIN], 'correct-horse-battery');

        $crawler = $this->client->request('GET', '/admin/login');
        $form = $crawler->filter('form')->form(['_username' => 'ADMIN@example.com ', '_password' => 'correct-horse-battery']);
        $this->client->submit($form, [], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseRedirects('/admin');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Добре дошли');

        $this->client->request('GET', '/izhod');
        $this->client->followRedirect();
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/login');
    }
}
