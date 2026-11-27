<?php

namespace App\Tests\Functional;

use App\Controller\Api\BotSpeakController;
use App\Entity\Wall;
use App\Entity\WallComment;
use App\Seed\ExampleContent;
use App\Tests\Support\WebDatabaseTestCase;

/** POST and GET /api/bot-speak: the JSON door to the wall of public comments. */
final class BotSpeakApiTest extends WebDatabaseTestCase
{
    private const PAGE = '/2026/11/27/sabirame-idei/';
    private const COMMENT = ['name' => 'Scraper_v2', 'message' => 'Здравейте.', 'os' => 'Ubuntu 24.04'];

    private Wall $wall;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wall = new Wall(ExampleContent::WALL_SLUG, ExampleContent::WALL_TITLE, new \DateTimeImmutable(ExampleContent::WALL_PUBLISHED_AT));
        $this->em->persist($this->wall);
        $this->em->flush();
    }

    /** @param array<string, string> $server */
    private function post(mixed $data, array $server = []): void
    {
        $this->client->request('POST', '/api/bot-speak', [], [], $server + ['CONTENT_TYPE' => 'application/json'], \is_string($data) ? $data : json_encode($data, \JSON_UNESCAPED_UNICODE));
    }

    /** @return array<array-key, mixed> */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function assertError(int $status, string $error): void
    {
        self::assertResponseStatusCodeSame($status);
        self::assertSame($error, $this->json()['error']);
    }

    public function testACommentIsSavedAndShownAtOnceWithTheApiMark(): void
    {
        $this->post(self::COMMENT, ['HTTP_USER_AGENT' => 'curl/8.7.1']);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $id = $this->json()['id'];
        self::assertSame('{"id":'.$id.'}', $this->client->getResponse()->getContent());

        $response = $this->client->getResponse();
        self::assertSame([], $response->headers->getCookies(), 'The API sets no cookies.');
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'), 'The API sends no CORS headers.');

        $comment = $this->em->getRepository(WallComment::class)->find($id);
        self::assertSame(WallComment::VIA_API, $comment->getVia());
        self::assertSame('curl/8.7.1', $comment->getUserAgent());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $comment->getIpHash());
        self::assertStringNotContainsString('127.0.0.1', $comment->getIpHash());

        $crawler = $this->client->request('GET', self::PAGE);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#comment-'.$id.' .fn', 'Scraper_v2');
        self::assertSelectorTextContains('#comment-'.$id.' .via', 'чрез API');
        self::assertSelectorTextContains('#comment-'.$id.' .comment-content', 'Здравейте.');
        self::assertSelectorTextContains('#comment-'.$id.' .comment-os', 'Операционна система: Ubuntu 24.04');
        self::assertSame('Един коментар за „Събираме идеи“', $crawler->filter('.comments-title')->text());
    }

    public function testValuesAreTrimmedAndUnknownFieldsIgnored(): void
    {
        $this->post(['name' => '  Бот  ', 'message' => "  ред едно\r\nред две  ", 'os' => ' Debian ', 'email' => 'x', 'is_hidden' => true, 'id' => 999]);

        self::assertResponseStatusCodeSame(201);
        $comment = $this->em->getRepository(WallComment::class)->find($this->json()['id']);
        self::assertSame(['Бот', "ред едно\nред две", 'Debian'], [$comment->getName(), $comment->getMessage(), $comment->getOs()]);
        self::assertFalse($comment->isHidden());
    }

    public function testAReplyGoesUnderTheRightCommentAndAReplyToAReplyStaysInTheThread(): void
    {
        $this->post(self::COMMENT);
        $first = $this->json()['id'];
        $this->post(['name' => 'Друг', 'message' => 'Без връзка.', 'os' => 'macOS']);
        $other = $this->json()['id'];

        $this->post(['name' => 'Отговарящ', 'message' => 'Отговор.', 'os' => 'Windows 11', 'reply_to_id' => $first]);
        self::assertResponseStatusCodeSame(201);
        $reply = $this->json()['id'];

        $this->post(['name' => 'Трети', 'message' => 'Отговор на отговора.', 'os' => 'Windows 11', 'reply_to_id' => $reply], ['REMOTE_ADDR' => '203.0.113.7']);
        self::assertResponseStatusCodeSame(201);
        $second = $this->json()['id'];

        $crawler = $this->client->request('GET', self::PAGE);
        $children = $crawler->filter('#comment-'.$first)->closest('li')->filter('.children .comment-body');
        self::assertSame(['comment-'.$reply, 'comment-'.$second], $children->each(fn ($node) => $node->attr('id')));
        self::assertCount(0, $crawler->filter('#comment-'.$other)->closest('li')->filter('.children'));

        $this->client->request('GET', '/api/bot-speak');
        self::assertSame([null, null, $first, $first], array_column($this->json(), 'reply_to_id'));
    }

    public function testWrongRequests(): void
    {
        $this->post(json_encode(self::COMMENT), ['CONTENT_TYPE' => 'text/plain']);
        $this->assertError(415, 'Очаква се application/json.');

        $this->post(['name' => 'Бот', 'message' => str_repeat('а', BotSpeakController::MAX_BODY), 'os' => 'Linux']);
        $this->assertError(413, 'Заявката е твърде голяма.');

        foreach (['{"name": ', '"текст"', '[1, 2]', ''] as $body) {
            $this->post($body);
            $this->assertError(400, 'Невалиден JSON.');
        }

        self::assertSame(0, $this->em->getRepository(WallComment::class)->count([]));
    }

    public function testInvalidFields(): void
    {
        $this->post(['name' => 'Бот', 'message' => 'Здравейте.']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('{"error":"Невалидни данни.","fields":{"os":"Полето е задължително."}}', $this->client->getResponse()->getContent());

        $cases = [
            'едно име от един знак' => [['name' => 'Б'], 'name', 'Името трябва да е между 2 и 40 знака.'],
            'твърде дълго име' => [['name' => str_repeat('б', 41)], 'name', 'Името трябва да е между 2 и 40 знака.'],
            'име не е низ' => [['name' => 42], 'name', 'Полето е задължително.'],
            'празен коментар' => [['message' => '   '], 'message', 'Полето е задължително.'],
            'твърде дълъг коментар' => [['message' => str_repeat('а', 1001)], 'message', 'Коментарът трябва да е най-много 1000 знака.'],
            'твърде дълга система' => [['os' => str_repeat('а', 61)], 'os', 'Операционната система трябва да е най-много 60 знака.'],
            'управляващ знак' => [['message' => "звън\x07"], 'message', 'Полето съдържа непозволени знаци.'],
            'нов ред в името' => [['name' => "Бот\nБот"], 'name', 'Полето съдържа непозволени знаци.'],
            'линк с https' => [['message' => 'Вижте https://example.test/x'], 'message', 'Линкове не са позволени.'],
            'линк с www' => [['message' => 'Вижте www.primer'], 'message', 'Линкове не са позволени.'],
            'домейн' => [['message' => 'Вижте primer.com за още'], 'message', 'Линкове не са позволени.'],
            'домейн в името' => [['name' => 'best-shop.bg'], 'name', 'Линкове не са позволени.'],
            'домейн в системата' => [['os' => 'Linux (moyat.net)'], 'os', 'Линкове не са позволени.'],
            'запазено име' => [['name' => 'Admin'], 'name', 'Името е заето.'],
            'несъществуващ коментар' => [['reply_to_id' => 99999], 'reply_to_id', 'Коментарът не съществува.'],
            'отговор не е число' => [['reply_to_id' => 'първия'], 'reply_to_id', 'Коментарът не съществува.'],
            'несъществуваща стена' => [['wall' => 'druga-stena'], 'wall', 'Стената не съществува.'],
        ];
        foreach ($cases as $case => [$change, $field, $message]) {
            $this->post($change + self::COMMENT);
            self::assertResponseStatusCodeSame(422, $case);
            self::assertSame(['error' => 'Невалидни данни.', 'fields' => [$field => $message]], $this->json(), $case);
        }

        self::assertSame(0, $this->em->getRepository(WallComment::class)->count([]));
    }

    public function testOrdinaryTextIsNotTakenForALink(): void
    {
        $this->post(['name' => 'Node.js бот', 'message' => 'Версия 24.04 работи.Нали? т.е. добре', 'os' => 'Ubuntu 24.04 LTS']);

        self::assertResponseStatusCodeSame(201);
    }

    public function testMarkupIsShownAsText(): void
    {
        $this->post(['name' => '<script>alert(1)</script>', 'message' => "<script>alert(2)</script>\n<b>удебелено</b>", 'os' => '<img src=x onerror=alert(3)>']);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', self::PAGE);
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString("&lt;script&gt;alert(2)&lt;/script&gt;<br />\n&lt;b&gt;удебелено&lt;/b&gt;", $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(3)&gt;', $html);
    }

    public function testTheSameTextFromTheSameSenderIsADuplicate(): void
    {
        $this->post(self::COMMENT);
        self::assertResponseStatusCodeSame(201);

        $this->post(['message' => '  ЗДРАВЕЙТЕ.  '] + self::COMMENT);
        $this->assertError(409, 'Открит е дублиран коментар.');

        $this->post(self::COMMENT, ['REMOTE_ADDR' => '203.0.113.7']);
        self::assertResponseStatusCodeSame(201, 'The same text from another sender is allowed.');
    }

    public function testTheFourthCommentWithinAMinuteIsTooFast(): void
    {
        foreach (['Едно.', 'Две.', 'Три.'] as $message) {
            $this->post(['message' => $message] + self::COMMENT);
            self::assertResponseStatusCodeSame(201);
        }

        $this->post(['message' => 'Четири.'] + self::COMMENT);
        $this->assertError(429, 'Публикувате коментари твърде бързо.');
        $retryAfter = (int) $this->client->getResponse()->headers->get('Retry-After');
        self::assertGreaterThan(0, $retryAfter);
        self::assertLessThanOrEqual(61, $retryAfter);

        $this->post(['message' => 'Четири.'] + self::COMMENT, ['REMOTE_ADDR' => '203.0.113.7']);
        self::assertResponseStatusCodeSame(201, 'Another sender is not affected.');
    }

    public function testIpv6SendersAreCountedByNetwork(): void
    {
        foreach (['1', '2', '3'] as $host) {
            $this->post(['message' => 'Съобщение '.$host] + self::COMMENT, ['REMOTE_ADDR' => '2001:db8:1:2::'.$host]);
            self::assertResponseStatusCodeSame(201);
        }

        $this->post(['message' => 'Съобщение 4'] + self::COMMENT, ['REMOTE_ADDR' => '2001:db8:1:2:aaaa:bbbb:cccc:dddd']);
        self::assertResponseStatusCodeSame(429);

        $this->post(['message' => 'Съобщение 4'] + self::COMMENT, ['REMOTE_ADDR' => '2001:db8:1:3::1']);
        self::assertResponseStatusCodeSame(201);
    }

    public function testDailyLimits(): void
    {
        $service = static::getContainer()->get(\App\Wall\WallCommentService::class);
        $sender = $service->ipHash('198.51.100.9');
        for ($i = 0; $i < 20; ++$i) {
            $this->em->persist($this->comment('Старо '.$i, $sender, new \DateTimeImmutable('-2 hours')));
        }
        $this->em->flush();

        $this->post(self::COMMENT, ['REMOTE_ADDR' => '198.51.100.9']);
        $this->assertError(429, 'Публикувате коментари твърде бързо.');
        self::assertGreaterThan(3600, (int) $this->client->getResponse()->headers->get('Retry-After'));

        $this->post(self::COMMENT);
        self::assertResponseStatusCodeSame(201, 'Other senders can still write.');

        // 200 comments on the wall within a day close it for everybody
        for ($i = 0; $i < 180; ++$i) {
            $this->em->persist($this->comment('Чуждо '.$i, hash('sha256', 'sender-'.$i), new \DateTimeImmutable('-3 hours')));
        }
        $this->em->flush();

        $this->post(['message' => 'Още едно.'] + self::COMMENT, ['REMOTE_ADDR' => '203.0.113.50']);
        $this->assertError(429, 'Публикувате коментари твърде бързо.');
    }

    public function testClosedCommentsAreRefused(): void
    {
        $this->em->getRepository(Wall::class)->find($this->wall->getId())->setIsOpen(false);
        $this->em->flush();

        $this->post(self::COMMENT);

        $this->assertError(403, 'Коментарите са затворени.');
    }

    public function testListHasTheVisibleCommentsOldestFirst(): void
    {
        $visible = $this->comment('Първи коментар', 'a');
        $hidden = $this->comment('Скрит коментар', 'b')->setIsHidden(true);
        $this->em->persist($visible);
        $this->em->persist($hidden);
        $this->em->persist($this->comment('Отговор на видимия', 'c', parent: $visible));
        $this->em->persist($this->comment('Отговор на скрития', 'd', parent: $hidden));
        $this->em->flush();

        $this->client->request('GET', '/api/bot-speak');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([], $this->client->getResponse()->headers->getCookies());
        self::assertStringContainsString('"message":"Първи коментар"', (string) $this->client->getResponse()->getContent(), 'Cyrillic is not escaped.');
        self::assertSame(['Първи коментар', 'Отговор на видимия'], array_column($this->json(), 'message'));
        self::assertSame(['id', 'name', 'message', 'os', 'reply_to_id', 'created_at'], array_keys($this->json()[0]));
        self::assertSame($visible->getId(), $this->json()[1]['reply_to_id']);
        self::assertSame($visible->getCreatedAt()->format(\DATE_ATOM), $this->json()[0]['created_at']);

        $this->client->request('GET', '/api/bot-speak?wall=sabirame-idei');
        self::assertCount(2, $this->json());

        $this->client->request('GET', '/api/bot-speak?wall=druga-stena');
        $this->assertError(404, 'Стената не съществува.');
    }

    public function testListIsLimitedToTheLastHundred(): void
    {
        for ($i = 1; $i <= 105; ++$i) {
            $this->em->persist($this->comment('Коментар '.$i, 'sender-'.$i, new \DateTimeImmutable('-2 days')));
        }
        $this->em->flush();

        $this->client->request('GET', '/api/bot-speak');

        $messages = array_column($this->json(), 'message');
        self::assertCount(100, $messages);
        self::assertSame(['Коментар 6', 'Коментар 105'], [$messages[0], $messages[99]]);
    }

    private function comment(string $message, string $sender, ?\DateTimeImmutable $at = null, ?WallComment $parent = null): WallComment
    {
        // the entity manager is cleared after every request
        $wall = $this->em->getRepository(Wall::class)->find($this->wall->getId());

        return new WallComment($wall, $parent, 'Тестер', $message, 'Linux', WallComment::VIA_API, str_pad($sender, 64, '0'), hash('sha256', $message), null, $at ?? new \DateTimeImmutable('-2 days'));
    }
}
