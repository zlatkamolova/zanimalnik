<?php

namespace App\Tests\Functional;

use App\Sign\DailySignService;
use App\Sign\SignBasis;
use App\Sign\SignInput;
use App\Tests\Support\WebDatabaseTestCase;
use App\Twig\Components\SignOfTheDay;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class LiveComponentsTest extends WebDatabaseTestCase
{
    use InteractsWithLiveComponents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadFixtures();
    }

    /** @return list<string> */
    private function titles(string $html): array
    {
        return (new Crawler($html))->filter('.quest-card-title')->each(fn (Crawler $n) => trim($n->text()));
    }

    public function testQuestBrowserFiltersWithoutReload(): void
    {
        $component = $this->createLiveComponent('QuestBrowser', [], $this->client);
        self::assertCount(8, $this->titles($component->render()->toString()));

        $component->set('path', 'vzor');
        self::assertEqualsCanonicalizing(['Седем жълти неща', 'Картата по памет', 'Нов път към вкъщи'], $this->titles($component->render()->toString()));

        $component->set('tag', 'bez-telefon');
        self::assertSame(['Нов път към вкъщи'], $this->titles($component->render()->toString()));

        $component->set('difficulty', 3);
        self::assertStringContainsString('Няма куестове по тези филтри.', $component->render()->toString());

        $component->call('reset');
        self::assertCount(8, $this->titles($component->render()->toString()));
    }

    public function testQuestBrowserIgnoresUnknownValues(): void
    {
        $component = $this->createLiveComponent('QuestBrowser', [], $this->client);
        $component->set('path', 'nyama-takava');
        $component->set('tag', '<script>');

        self::assertCount(8, $this->titles($component->render()->toString()));
    }

    public function testSignOfTheDayRevealAndChange(): void
    {
        $component = $this->createLiveComponent('SignOfTheDay', [], $this->client);
        self::assertStringContainsString('Покажи знака', $component->render()->toString());

        $component->set('basis', 'zodiac')->set('zodiac', 'lav')->call('reveal');
        $html = $component->render()->toString();

        $expected = static::getContainer()->get(DailySignService::class)->forToday(new SignInput(SignBasis::Zodiac, 'lav'));
        self::assertStringContainsString($expected->colorName, $html);
        self::assertStringContainsString('число '.$expected->number, $html);
        self::assertStringContainsString('data-daily-sign-cookie-value-value="zodiac:lav"', $html);
        self::assertStringContainsString('is-new', $html, 'the reveal animates');

        $component->call('change');
        $html = $component->render()->toString();
        self::assertStringContainsString('Покажи знака', $html);
        self::assertStringContainsString('data-daily-sign-cookie-value-value=""', $html, 'the cookie gets deleted');
    }

    public function testSignOfTheDayValidatesInBulgarian(): void
    {
        $component = $this->createLiveComponent('SignOfTheDay', [], $this->client);

        $component->set('basis', 'name')->set('name', '   ')->call('reveal');
        self::assertStringContainsString('Напишете име.', $component->render()->toString());

        $component->set('name', str_repeat('я', 41))->call('reveal');
        self::assertStringContainsString('Името може да е най-много 40 знака.', $component->render()->toString());

        $component->set('basis', 'role')->set('role', 'nyama')->call('reveal');
        self::assertStringContainsString('Изберете роля.', $component->render()->toString());
    }

    public function testReturningVisitorSeesTheSignFromTheCookieWithoutAnimation(): void
    {
        $this->client->request('GET', '/'); // first visit of the day creates the daily challenges
        $before = $this->rowCounts();

        $this->client->getCookieJar()->set(new Cookie(SignOfTheDay::COOKIE, 'name:'.rawurlencode('мария')));
        $crawler = $this->client->request('GET', '/');

        $expected = static::getContainer()->get(DailySignService::class)->forToday(new SignInput(SignBasis::Name, 'Мария'));
        self::assertSelectorTextContains('.sign-color-name', $expected->colorName);
        self::assertSelectorTextContains('.sign-for', 'Мария');
        self::assertCount(0, $crawler->filter('.sign-result.is-new'));
        // the name only travels in the visitor's cookie: not a single row is added anywhere
        self::assertSame($before, $this->rowCounts());
    }

    /** @return array<string, int> table => number of rows */
    private function rowCounts(): array
    {
        $connection = $this->em->getConnection();
        $counts = [];
        foreach ($connection->createSchemaManager()->listTableNames() as $table) {
            $counts[$table] = (int) $connection->fetchOne("SELECT COUNT(*) FROM `$table`");
        }

        return $counts;
    }

    public function testBrokenCookieShowsTheForm(): void
    {
        $this->client->getCookieJar()->set(new Cookie(SignOfTheDay::COOKIE, 'role:hacker'));
        $this->client->request('GET', '/');

        self::assertSelectorTextContains('.sign', 'Покажи знака');
    }
}
