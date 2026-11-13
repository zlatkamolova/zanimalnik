<?php

namespace App\DataFixtures;

use App\Seed\ContentSeeder;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Local example data (`make db`): the same content production gets on its first deploy
 * (see App\Seed\ExampleContent), plus one draft to check that drafts stay hidden.
 */
final class AppFixtures extends Fixture
{
    public function __construct(private readonly ContentSeeder $seeder)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->seeder->seed(withDraft: true);
    }
}
