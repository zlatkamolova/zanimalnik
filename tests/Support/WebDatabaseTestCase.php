<?php

namespace App\Tests\Support;

use App\Entity\User;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Browser tests on the test database (emptied before every test). */
abstract class WebDatabaseTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['HTTP_HOST' => 'localhost']);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new ORMPurger($this->em))->purge();
    }

    /** Loads the same example data as `make db` (4 paths, 3 roles, 8 quests + 1 draft …). */
    protected function loadFixtures(): void
    {
        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        (new ORMExecutor($this->em, new ORMPurger($this->em)))->execute($loader->getFixtures());
        $this->em->clear();
    }

    protected function createUser(string $email, array $roles, string $password = 'test-password-123'): User
    {
        $user = (new User())->setEmail($email)->setRoles($roles);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
