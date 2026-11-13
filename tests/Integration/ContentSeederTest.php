<?php

namespace App\Tests\Integration;

use App\Entity\Path;
use App\Entity\Quest;
use App\Entity\Setting;
use App\Entity\SignRole;
use App\Entity\Tag;
use App\Seed\ContentSeeder;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\Filesystem\Filesystem;

/** The one-time content of the first production deploy ("option 2"). */
final class ContentSeederTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new Filesystem())->remove(self::getContainer()->getParameter('app.uploads_dir'));
    }

    public function testFillsAnEmptyDatabaseWithPublishedExamplesAndNoDraft(): void
    {
        $created = self::getContainer()->get(ContentSeeder::class)->seed();

        self::assertSame(['пътеки' => 4, 'роли' => 3, 'тагове' => 7, 'куеста' => 8], $created);
        self::assertSame(4, $this->em->getRepository(Path::class)->count([]));
        self::assertSame(3, $this->em->getRepository(SignRole::class)->count([]));
        self::assertSame(7, $this->em->getRepository(Tag::class)->count([]));
        self::assertSame(8, $this->em->getRepository(Quest::class)->count(['isPublished' => true]));
        self::assertSame(0, $this->em->getRepository(Quest::class)->count(['isPublished' => false]), 'no draft on production');
        self::assertNotSame('', $this->em->find(Setting::class, Setting::ABOUT_BODY)?->getValue());

        $pdfDir = self::getContainer()->getParameter('app.quest_pdf_dir');
        foreach ($this->em->getRepository(Quest::class)->findAll() as $quest) {
            if ($quest->getPdfPath()) {
                self::assertFileExists($pdfDir.'/'.$quest->getPdfPath());
            }
        }
    }

    public function testNeverTouchesADatabaseWithContent(): void
    {
        $seeder = self::getContainer()->get(ContentSeeder::class);
        $seeder->seed();

        // the admin edits a quest …
        $quest = $this->em->getRepository(Quest::class)->findOneBy(['slug' => 'mnogolikata-duma']);
        $quest->setTitle('Многоликата дума (моя версия)');
        $this->em->flush();

        // … and the next deploy seeds again
        self::assertSame([], $seeder->seed());
        $this->em->clear();
        self::assertSame('Многоликата дума (моя версия)', $this->em->getRepository(Quest::class)->findOneBy(['slug' => 'mnogolikata-duma'])->getTitle());
        self::assertSame(8, $this->em->getRepository(Quest::class)->count([]));
    }
}
