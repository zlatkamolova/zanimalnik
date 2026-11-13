<?php

namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Cron, every 5 minutes (the hosting has no SSH, so this replaces "run the migrations after deploy"):
 *   /opt/cpanel/ea-php85/root/usr/bin/php /home/zaniskyu/zanimalnik/bin/console app:post-deploy
 *
 * When GitHub Actions has uploaded var/deploy.flag (always the last file of a deploy):
 *   1. doctrine:migrations:migrate
 *   2. app:seed            (fills an empty database once — first deploy only)
 *   3. cache:clear         (rebuilds the production cache for the new code)
 *   → result appended to var/log/post-deploy.log, flag deleted.
 * Without the flag it does nothing (apart from the admin check below).
 *
 * Also on every run (after the steps above when there is a flag): if ADMIN_EMAIL and ADMIN_PASSWORD
 * are set in .env.local, app:create-admin (does nothing when that admin already exists) —
 * the cPanel way to create the first admin.
 *
 * Every step runs as a separate `bin/console` process, so cache:clear never pulls the rug
 * from under the process that is running it.
 */
#[AsCommand(name: 'app:post-deploy', description: 'След деплой: миграции, начално съдържание и изчистване на кеша (ако има var/deploy.flag)')]
final class PostDeployCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%kernel.logs_dir%')] private readonly string $logsDir,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Изпълни стъпките и без var/deploy.flag');
    }

    public function flagPath(): string
    {
        return $this->projectDir.'/var/deploy.flag';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // cron fires every 5 minutes; a slow migration must never run twice at the same time
        $lock = $this->lockFactory->createLock('post-deploy', 600);
        if (!$lock->acquire()) {
            $output->writeln('Друго изпълнение на app:post-deploy още работи.', OutputInterface::VERBOSITY_VERBOSE);

            return Command::SUCCESS;
        }

        try {
            if (!is_file($this->flagPath()) && !$input->getOption('force')) {
                $this->createAdminFromEnv();

                return Command::SUCCESS; // nothing was deployed — stay silent for cron
            }

            $flag = is_file($this->flagPath()) ? trim((string) file_get_contents($this->flagPath())) : '(--force)';
            $this->log(\sprintf('=== Деплой %s, флаг: %s', date('Y-m-d H:i:s'), $flag ?: '-'));

            $ok = true;
            foreach ([
                ['doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration'],
                ['app:seed', '--no-interaction'],
                ['cache:clear', '--no-interaction'],
            ] as $step) {
                if (!$this->runStep($step, $output)) {
                    $ok = false;
                    break; // e.g. a failed migration: don't continue with a half-updated database
                }
            }

            // Delete the flag even after a failure so cron doesn't retry every 5 minutes;
            // the log says what went wrong, and the next deploy brings a new flag.
            $this->filesystem->remove($this->flagPath());
            $this->log($ok ? '=== Готово.' : '=== СПРЯНО заради грешка (виж по-горе).');

            // after the migrations, so on the very first deploy the users table already exists
            if ($ok) {
                $this->createAdminFromEnv();
            }

            return $ok ? Command::SUCCESS : Command::FAILURE;
        } finally {
            $lock->release();
        }
    }

    private function createAdminFromEnv(): void
    {
        if (!$this->env('ADMIN_EMAIL') || !$this->env('ADMIN_PASSWORD')) {
            return;
        }

        // ADMIN_RESET_PASSWORD=1 also changes the password of an existing admin (forgotten password)
        $reset = \in_array(strtolower((string) $this->env('ADMIN_RESET_PASSWORD')), ['1', 'true', 'yes', 'да'], true);

        $process = $this->console(['app:create-admin', '--no-interaction', ...($reset ? ['--reset-password'] : [])]);
        $process->run();
        if (str_contains($process->getOutput(), 'е създаден')) {
            $this->log('Създаден е администратор '.$this->env('ADMIN_EMAIL').'. Изтрийте ADMIN_EMAIL и ADMIN_PASSWORD от .env.local.');
        } elseif (str_contains($process->getOutput(), 'е сменена')) {
            $this->log('Сменена е паролата на '.$this->env('ADMIN_EMAIL').'. Изтрийте ADMIN_EMAIL, ADMIN_PASSWORD и ADMIN_RESET_PASSWORD от .env.local.');
        } elseif (!$process->isSuccessful()) {
            $this->log('Администраторът НЕ е създаден: '.trim($process->getOutput().$process->getErrorOutput()));
        }
    }

    /** @param list<string> $arguments */
    private function runStep(array $arguments, OutputInterface $output): bool
    {
        $process = $this->console($arguments);
        $process->run();

        $text = trim($process->getOutput()."\n".$process->getErrorOutput());
        $this->log(\sprintf('$ bin/console %s → %s', implode(' ', $arguments), $process->isSuccessful() ? 'OK' : 'ГРЕШКА (код '.$process->getExitCode().')'));
        if ('' !== $text) {
            $this->log(preg_replace('/^/m', '    ', $text));
        }
        $output->writeln($text, OutputInterface::VERBOSITY_VERBOSE);

        if (!$process->isSuccessful()) {
            $this->logger->error('Post-deploy step failed: {cmd}', ['cmd' => implode(' ', $arguments), 'output' => $text]);
        }

        return $process->isSuccessful();
    }

    /** @param list<string> $arguments */
    private function console(array $arguments): Process
    {
        $php = (new PhpExecutableFinder())->find(false) ?: \PHP_BINARY;

        $process = new Process(
            [$php, $this->projectDir.'/bin/console', ...$arguments, '--env='.$this->environment],
            $this->projectDir,
            ['SHELL_VERBOSITY' => '0'],
        );
        $process->setTimeout(600);

        return $process;
    }

    private function log(string $line): void
    {
        $this->filesystem->appendToFile($this->logsDir.'/post-deploy.log', $line."\n");
    }

    private function env(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
