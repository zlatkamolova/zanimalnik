<?php

namespace App\Tests\Functional;

use App\Entity\Path;
use App\Entity\Quest;
use App\Entity\SignRole;
use App\Tests\Support\WebDatabaseTestCase;

/** Acceptance criterion: migrate + fixtures:load gives 4 paths, 3 roles, ≥ 8 quests (2 per path) with a sample PDF. */
final class FixturesTest extends WebDatabaseTestCase
{
    public function testExampleDataMatchesTheBrief(): void
    {
        $this->loadFixtures();

        $paths = $this->em->getRepository(Path::class)->findBy([], ['sortOrder' => 'ASC']);
        self::assertSame(['vzor', 'slovo', 'orakul', 'krivite-pateki'], array_map(fn (Path $p) => $p->getSlug(), $paths));
        self::assertCount(3, $this->em->getRepository(SignRole::class)->findAll());

        $published = $this->em->getRepository(Quest::class)->findBy(['isPublished' => true]);
        self::assertGreaterThanOrEqual(8, \count($published));
        foreach ($paths as $path) {
            $own = array_filter($published, fn (Quest $q) => $q->getPath() === $path);
            self::assertGreaterThanOrEqual(2, \count($own), $path->getName());
        }

        $word = $this->em->getRepository(Quest::class)->findOneBy(['title' => 'Многоликата дума']);
        self::assertNotNull($word);
        self::assertSame('slovo', $word->getPath()->getSlug());
        self::assertNotNull($word->getPdfPath());

        $pdfDir = static::getContainer()->getParameter('app.quest_pdf_dir');
        self::assertFileExists($pdfDir.'/'.$word->getPdfPath());
        self::assertStringStartsWith('%PDF', file_get_contents($pdfDir.'/'.$word->getPdfPath(), length: 4));
    }
}
