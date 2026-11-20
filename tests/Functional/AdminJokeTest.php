<?php

namespace App\Tests\Functional;

use App\Entity\Joke;
use App\Entity\User;
use App\Service\JokeImageProcessor;
use App\Tests\Support\WebDatabaseTestCase;
use Symfony\Component\Filesystem\Filesystem;

/** The admin adds jokes, anecdotes and memes; the home page shows one of them. */
final class AdminJokeTest extends WebDatabaseTestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/zanimalnik-joke-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->tmp);
        (new Filesystem())->remove(static::getContainer()->getParameter('app.uploads_dir'));
        $this->client->loginUser($this->createUser('admin@example.com', [User::ROLE_ADMIN]));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tmp);
        parent::tearDown();
    }

    private function submitNew(array $values, ?string $image = null): void
    {
        $crawler = $this->client->request('GET', '/admin/joke/new');
        $form = $crawler->filter('form[name="Joke"]')->form();
        $form->setValues($values);
        if ($image) {
            $form['Joke[imagePath][file]']->upload($image);
        }
        $this->client->submit($form, [], ['HTTP_ORIGIN' => 'http://localhost']);
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 160, 60));
        imagepng($image, $file = $this->tmp.'/меме.png');

        return $file;
    }

    public function testTextAnecdoteAppearsOnTheHomePage(): void
    {
        $this->submitNew(['Joke[type]' => Joke::TYPE_ANECDOTE, 'Joke[text]' => "— Чук-чук!\n— Кой е?"]);
        self::assertResponseRedirects();

        $crawler = $this->client->request('GET', '/');
        self::assertSelectorTextSame('#joke-title', 'Виц на деня');
        self::assertSelectorTextContains('.joke-text', '— Чук-чук!');
        self::assertCount(1, $crawler->filter('.joke-text br'), 'line breaks are kept');
        self::assertSelectorNotExists('.joke-image');
    }

    public function testMemeImageIsStoredResizedAndShownWithItsDescription(): void
    {
        $this->submitNew(
            ['Joke[type]' => Joke::TYPE_MEME, 'Joke[imageAlt]' => 'Котка гледа карта наобратно'],
            $this->png(2400, 1200),
        );
        self::assertResponseRedirects();

        $joke = $this->em->getRepository(Joke::class)->findOneBy([]);
        $file = static::getContainer()->getParameter('app.joke_image_dir').'/'.$joke->getImagePath();
        self::assertSame([JokeImageProcessor::MAX_SIZE, 600], \array_slice(getimagesize($file), 0, 2));

        $this->client->request('GET', '/');
        self::assertSelectorTextSame('#joke-title', 'Меме на деня');
        self::assertSelectorExists('img.joke-image[alt="Котка гледа карта наобратно"][src^="/uploads/jokes/"]');
    }

    public function testEmptyEntryAndImageWithoutDescriptionAreRejectedInBulgarian(): void
    {
        $this->submitNew(['Joke[type]' => Joke::TYPE_JOKE, 'Joke[text]' => '  ']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Въведете текст или качете изображение.');

        $this->submitNew(['Joke[type]' => Joke::TYPE_MEME], $this->png(300, 300));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Опишете с няколко думи какво има на изображението.');

        self::assertSame(0, $this->em->getRepository(Joke::class)->count([]));
    }
}
