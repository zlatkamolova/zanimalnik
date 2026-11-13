<?php

namespace App\Tests\Functional;

use App\Entity\Path;
use App\Entity\Quest;
use App\Entity\User;
use App\Service\CoverImageProcessor;
use App\Tests\Support\WebDatabaseTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Acceptance criterion: the admin can create, edit and publish a quest with a PDF and a cover. */
final class AdminQuestUploadTest extends WebDatabaseTestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/zanimalnik-upload-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->tmp);
        (new Filesystem())->remove(static::getContainer()->getParameter('app.uploads_dir'));

        $this->loadFixtures();
        $this->client->loginUser($this->createUser('admin@example.com', [User::ROLE_ADMIN]));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tmp);
        parent::tearDown();
    }

    private function jpeg(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 80, 40));
        imagejpeg($image, $file = $this->tmp.'/корица.jpg', 85);

        return new UploadedFile($file, 'корица.jpg', 'image/jpeg', null, true);
    }

    private function pdf(): UploadedFile
    {
        copy(\dirname(__DIR__, 2).'/resources/seed/mnogolikata-duma.pdf', $file = $this->tmp.'/list.pdf');

        return new UploadedFile($file, 'list.pdf', 'application/pdf', null, true);
    }

    private function newQuestForm(array $values, array $files): void
    {
        $crawler = $this->client->request('GET', '/admin/quest/new');
        $form = $crawler->filter('form[name="Quest"]')->form();
        $form->setValues($values);
        foreach ($files as $field => $file) {
            $form["Quest[$field][file]"]->upload($file->getPathname());
        }
        $this->client->submit($form, [], ['HTTP_ORIGIN' => 'http://localhost']);
    }

    public function testCreatePublishWithPdfAndCover(): void
    {
        $slovo = $this->em->getRepository(Path::class)->findOneBy(['slug' => 'slovo']);

        $this->newQuestForm([
            'Quest[title]' => 'Ехо в стълбището',
            'Quest[summary]' => 'Слушай как отговаря сградата.',
            'Quest[body]' => '<p>Тяло на куеста.</p><h2>Правила</h2><ul><li>Едно</li></ul>',
            'Quest[path]' => (string) $slovo->getId(),
            'Quest[difficulty]' => '2',
            'Quest[duration]' => 'една вечер',
            'Quest[isPublished]' => true,
        ], ['coverImagePath' => $this->jpeg(2400, 1600), 'pdfPath' => $this->pdf()]);

        self::assertResponseRedirects();

        $this->em->clear();
        $quest = $this->em->getRepository(Quest::class)->findOneBy(['title' => 'Ехо в стълбището']);
        self::assertNotNull($quest);
        self::assertSame('eho-v-stalbishteto', $quest->getSlug());
        self::assertTrue($quest->isPublished());
        self::assertNotNull($quest->getPublishedAt());

        // files are stored, the cover is resized and has a card copy
        $coverDir = static::getContainer()->getParameter('app.quest_cover_dir');
        $pdfDir = static::getContainer()->getParameter('app.quest_pdf_dir');
        self::assertFileExists($pdfDir.'/'.$quest->getPdfPath());
        [$w, $h] = getimagesize($coverDir.'/'.$quest->getCoverImagePath());
        self::assertSame([1600, 1067], [$w, $h]);
        self::assertFileExists($coverDir.'/'.CoverImageProcessor::cardFileName($quest->getCoverImagePath()));

        // visitors see it, with the download button
        $this->client->request('GET', '/kuest/eho-v-stalbishteto');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ехо в стълбището');
        self::assertSelectorTextContains('.quest-sheet a[download]', 'Изтегли листа на куеста');
        self::assertSelectorExists('.pdf-preview object[type="application/pdf"]');
        self::assertSelectorExists('img.quest-cover[alt*="Ехо в стълбището"]');
    }

    public function testDraftStaysHiddenUntilPublished(): void
    {
        $slovo = $this->em->getRepository(Path::class)->findOneBy(['slug' => 'slovo']);
        $this->newQuestForm([
            'Quest[title]' => 'Тиха чернова',
            'Quest[summary]' => 'Още не е готова.',
            'Quest[path]' => (string) $slovo->getId(),
            'Quest[difficulty]' => '1',
            'Quest[duration]' => 'един час',
        ], []);
        self::assertResponseRedirects();

        $this->client->request('GET', '/kuest/tiha-chernova');
        self::assertResponseStatusCodeSame(404);

        // publish it through the edit form
        $quest = $this->em->getRepository(Quest::class)->findOneBy(['slug' => 'tiha-chernova']);
        $crawler = $this->client->request('GET', '/admin/quest/'.$quest->getId().'/edit');
        $form = $crawler->filter('form[name="Quest"]')->form();
        $form['Quest[isPublished]']->tick();
        $this->client->submit($form, [], ['HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseRedirects();

        $this->client->request('GET', '/kuest/tiha-chernova');
        self::assertResponseIsSuccessful();
    }

    public function testWrongFileTypesAreRejectedInBulgarian(): void
    {
        $slovo = $this->em->getRepository(Path::class)->findOneBy(['slug' => 'slovo']);
        file_put_contents($fake = $this->tmp.'/fake.pdf', 'това не е PDF');

        $this->newQuestForm([
            'Quest[title]' => 'Грешни файлове',
            'Quest[summary]' => 'Проверка.',
            'Quest[path]' => (string) $slovo->getId(),
            'Quest[difficulty]' => '1',
            'Quest[duration]' => 'един час',
        ], ['pdfPath' => new UploadedFile($fake, 'fake.pdf', 'application/pdf', null, true)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Файлът трябва да е PDF.');
        self::assertNull($this->em->getRepository(Quest::class)->findOneBy(['title' => 'Грешни файлове']));
    }

    public function testRequiredFieldsHaveBulgarianMessages(): void
    {
        $this->newQuestForm(['Quest[title]' => '', 'Quest[summary]' => '', 'Quest[duration]' => ''], []);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Заглавието е задължително.');
        self::assertSelectorTextContains('body', 'Краткото описание е задължително.');
        self::assertSelectorTextContains('body', 'Продължителността е задължителна.');
    }
}
