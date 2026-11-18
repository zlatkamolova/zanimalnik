<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Support\WebDatabaseTestCase;

/** GoatCounter: only when GOATCOUNTER_CODE is set (the server), only on public pages, and still no cookies. */
final class AnalyticsTest extends WebDatabaseTestCase
{
    private const SCRIPT = 'script[data-goatcounter]';

    /** Tests named "...WithCode" run with GOATCOUNTER_CODE set, as on the server (.env.prod). */
    protected function setUp(): void
    {
        if (str_ends_with($this->name(), 'WithCode')) {
            $_SERVER['GOATCOUNTER_CODE'] = $_ENV['GOATCOUNTER_CODE'] = 'zanimalnik';
        }
        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['GOATCOUNTER_CODE'], $_ENV['GOATCOUNTER_CODE']);
        parent::tearDown();
    }

    public function testNoCounterWithoutCode(): void
    {
        $this->loadFixtures();
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(self::SCRIPT);
    }

    public function testCounterOnPublicPagesWithoutCookiesWithCode(): void
    {
        $this->loadFixtures();

        foreach (['/', '/kuesti', '/kuest/mnogolikata-duma', '/za-sajta'] as $url) {
            $crawler = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSame('https://zanimalnik.goatcounter.com/count', $crawler->filter(self::SCRIPT)->attr('data-goatcounter'), $url);
            self::assertSame('https://gc.zgo.at/count.js', $crawler->filter(self::SCRIPT)->attr('src'));
            self::assertSame([], $this->client->getResponse()->headers->getCookies(), $url);
        }
    }

    public function testNoCounterInAdminWithCode(): void
    {

        $this->client->request('GET', '/admin/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(self::SCRIPT);

        $this->client->loginUser($this->createUser('admin@example.com', [User::ROLE_ADMIN]));
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(self::SCRIPT);
    }
}
