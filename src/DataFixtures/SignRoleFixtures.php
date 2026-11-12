<?php

namespace App\DataFixtures;

use App\Entity\SignRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class SignRoleFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach (['Пазителят', 'Скитникът', 'Тълкувателят'] as $i => $name) {
            $manager->persist((new SignRole())->setName($name)->setSortOrder(($i + 1) * 10));
        }

        $manager->flush();
    }
}
