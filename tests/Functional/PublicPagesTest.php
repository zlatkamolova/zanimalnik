<?php

namespace App\Tests\Functional;

use App\Tests\Support\WebDatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DomCrawler\Crawler;

final class PublicPagesTest extends WebDatabaseTestCase
{
    /** Latin words that are fine on a Bulgarian page. */
    private const ALLOWED_LATIN = ['PDF', 'zanimalnik', 'com'];

    public static function pages(): iterable
    {
        yield 'начало' => ['/'];
        yield 'всички куестове' => ['/kuesti'];
        yield 'филтрирани' => ['/kuesti?pateka=slovo&trudnost=2&tag=s-tefter'];
        yield 'пътека Взор' => ['/pateki/vzor'];
        yield 'пътека Кривите пътеки' => ['/pateki/krivite-pateki'];
        yield 'куест с PDF' => ['/kuest/mnogolikata-duma'];
        yield 'куест на кръстопът' => ['/kuest/nov-pat-kam-vkashti'];
        yield 'за сайта' => ['/za-sajta'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadFixtures();
    }

    #[DataProvider('pages')]
    public function testPageWorksAndHasSeoTags(string $url): void
    {
        $crawler = $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSame('bg', $crawler->filter('html')->attr('lang'));
        self::assertCount(1, $crawler->filter('h1'));
        self::assertNotEmpty($crawler->filter('title')->text());
        foreach (['meta[name="description"]', 'link[rel="canonical"]', 'meta[property="og:title"]', 'meta[property="og:description"]', 'meta[property="og:url"]'] as $selector) {
            self::assertNotEmpty($crawler->filter($selector)->attr('content') ?? $crawler->filter($selector)->attr('href'), $selector);
        }
    }

    #[DataProvider('pages')]
    public function testNoEnglishTextOnPublicPages(string $url): void
    {
        $crawler = $this->client->request('GET', $url);

        self::assertSame([], $this->latinWords($crawler), 'English words on '.$url);
    }

    #[DataProvider('pages')]
    public function testNoCookiesOnPublicPages(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertSame([], $this->client->getResponse()->headers->getCookies(), 'Only the sign-of-the-day cookie is allowed, and it is set in the browser.');
    }

    public function testDraftIsHiddenEverywhere(): void
    {
        $this->client->request('GET', '/kuest/shepotat-na-stalbishteto');
        self::assertResponseStatusCodeSame(404);

        foreach (['/kuesti', '/pateki/slovo', '/'] as $url) {
            $this->client->request('GET', $url);
            self::assertStringNotContainsString('Шепотът на стълбището', (string) $this->client->getResponse()->getContent(), $url);
        }

        $this->client->request('GET', '/sitemap.xml');
        self::assertStringNotContainsString('shepotat-na-stalbishteto', (string) $this->client->getResponse()->getContent());
    }

    public function testPathPageListsQuestsAtACrossroads(): void
    {
        // "Нов път към вкъщи" belongs to Кривите пътеки and also to Взор
        $this->client->request('GET', '/pateki/vzor');
        self::assertSelectorTextContains('.card-grid', 'Нов път към вкъщи');
    }

    public function testQuestPageShowsPdfBlockOnlyWhenThereIsAPdf(): void
    {
        $this->client->request('GET', '/kuest/mnogolikata-duma');
        self::assertSelectorTextContains('a[download]', 'Изтегли листа на куеста');
        self::assertSelectorExists('details.pdf-preview object');

        $this->client->request('GET', '/kuest/nekazanoto');
        self::assertSelectorNotExists('a[download]');
    }

    public function testFilterWorksWithoutJavaScript(): void
    {
        $crawler = $this->client->request('GET', '/kuesti?pateka=orakul&trudnost=2');

        $titles = $crawler->filter('.quest-card-title')->each(fn (Crawler $n) => trim($n->text()));
        self::assertSame(['Три прогнози за деня'], $titles);
        self::assertSelectorTextContains('.results-count', '1 куест');
    }

    public function testHomeShowsOneDailyChallengePerPath(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertSelectorTextContains('#daily-title', 'Предизвикателства на деня');
        self::assertCount(4, $crawler->filter('#daily-title')->closest('section')->filter('.quest-card'));
    }

    public function testSitemapAndRobots(): void
    {
        $this->client->request('GET', '/sitemap.xml');
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string((string) $this->client->getResponse()->getContent());
        $locs = array_map('strval', $xml->xpath('//*[local-name()="loc"]'));
        self::assertCount(3 + 4 + 8, $locs, 'home, quests, about + 4 paths + 8 published quests');
        self::assertContains('http://localhost/kuest/mnogolikata-duma', $locs);

        $this->client->request('GET', '/robots.txt');
        self::assertStringContainsString('Disallow: /admin', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', (string) $this->client->getResponse()->getContent());
    }

    public function testNotFoundPageIsInTheSiteStyle(): void
    {
        // the styled error page is what visitors get in production (debug off)
        self::ensureKernelShutdown();
        $client = static::createClient(['debug' => false]);

        $crawler = $client->request('GET', '/nyama-takava-stranitsa');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Тази пътека не води наникъде');
        self::assertSelectorExists('a[href="/kuesti"]');
        self::assertSame([], $this->latinWords($crawler));
    }

    public function testEnglishDetectorActuallyCatchesEnglish(): void
    {
        // control: proves the check above is not silently passing
        $crawler = new Crawler('<html><head><meta name="description" content="Quests"></head><body>Здравей <b>world</b> <img alt="Cover" src="x"> <button aria-label="Close"></button> PDF zanimalnik.com<script>var english = 1</script></body></html>');

        self::assertEqualsCanonicalizing(['world', 'Cover', 'Close', 'Quests'], $this->latinWords($crawler));
    }

    /** Visible text, titles and accessibility texts that contain Latin words. */
    private function latinWords(Crawler $crawler): array
    {
        $crawler = $crawler->filter('html');
        $node = $crawler->getNode(0)->cloneNode(true);
        foreach (['script', 'style', 'svg', 'noscript'] as $tag) {
            foreach (iterator_to_array($node->getElementsByTagName($tag)) as $el) {
                $el->parentNode->removeChild($el);
            }
        }
        $texts = [$node->textContent];
        foreach ((new Crawler($node))->filter('[alt], [title], [aria-label], [placeholder], meta[name="description"], meta[property^="og:title"], meta[property^="og:description"]') as $el) {
            foreach (['alt', 'title', 'aria-label', 'placeholder', 'content'] as $attr) {
                if ($el->hasAttribute($attr) && 'content' !== $attr || ($el->nodeName === 'meta' && 'content' === $attr)) {
                    $texts[] = $el->getAttribute($attr);
                }
            }
        }

        preg_match_all('/\b[A-Za-z]{2,}\b/', implode(' ', $texts), $m);

        return array_values(array_unique(array_diff($m[0], self::ALLOWED_LATIN)));
    }
}
