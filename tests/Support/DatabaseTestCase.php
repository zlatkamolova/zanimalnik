<?php

namespace App\Tests\Support;

use App\Entity\Path;
use App\Entity\Quest;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Kernel test on the separate test database (zanimalnik_test), emptied before every test.
 * Create it once with:  make console c="doctrine:migrations:migrate -n --env=test"
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        (new ORMPurger($this->em))->purge();
    }

    protected function createPath(string $name, int $sortOrder = 10): Path
    {
        $path = (new Path())->setName($name)->setSymbol('eye')->setColor('#336699')->setSortOrder($sortOrder);
        $this->em->persist($path);

        return $path;
    }

    protected function createQuest(Path $path, string $title, bool $published = true, bool $eligible = true): Quest
    {
        $quest = (new Quest())
            ->setPath($path)
            ->setTitle($title)
            ->setSummary('Кратко описание.')
            ->setBody('<p>Текст.</p>')
            ->setDifficulty(1)
            ->setDuration('един ден')
            ->setIsPublished($published)
            ->setIsDailyEligible($eligible)
            ->setPublishedAt($published ? new \DateTimeImmutable('-30 days') : null);
        $this->em->persist($quest);

        return $quest;
    }
}
