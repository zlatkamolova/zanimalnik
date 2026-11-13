<?php

namespace App\Tests\Integration;

use App\Command\PostDeployCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The full run (migrations, seed, cache:clear in sub-processes) was rehearsed on a simulated
 * SuperHosting layout; here we check the cron behaviour that must hold every 5 minutes.
 */
final class PostDeployCommandTest extends KernelTestCase
{
    public function testWithoutFlagItDoesNothingAndStaysSilent(): void
    {
        self::bootKernel();
        $command = self::getContainer()->get(PostDeployCommand::class);
        $command->setApplication(new Application(self::$kernel));

        $log = self::getContainer()->getParameter('kernel.logs_dir').'/post-deploy.log';
        (new Filesystem())->remove([$command->flagPath(), $log]);

        $tester = new CommandTester($command);
        $tester->execute([], ['interactive' => false]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('', trim($tester->getDisplay()), 'cron mails any output — there must be none');
        self::assertFileDoesNotExist($log);
    }

    public function testFlagLivesInVarOfTheProject(): void
    {
        self::bootKernel();
        $command = self::getContainer()->get(PostDeployCommand::class);

        // GitHub Actions uploads the flag to <project>/var/deploy.flag
        self::assertSame(self::getContainer()->getParameter('kernel.project_dir').'/var/deploy.flag', $command->flagPath());
    }
}
