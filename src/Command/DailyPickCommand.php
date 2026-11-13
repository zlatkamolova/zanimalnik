<?php

namespace App\Command;

use App\Daily\DailyPickService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Cron, every day at 00:05 (Europe/Sofia):
 *   /opt/cpanel/ea-php85/root/usr/bin/php /home/zaniskyu/zanimalnik/bin/console app:daily-pick
 * Safe to run any number of times: existing (and manually chosen) days are left alone.
 */
#[AsCommand(name: 'app:daily-pick', description: 'Избира предизвикателствата на деня (по един куест за всяка пътека)')]
final class DailyPickCommand extends Command
{
    public function __construct(private readonly DailyPickService $daily)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Ден във формат ГГГГ-ММ-ДД (по подразбиране днес)')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Колко поредни дни да се попълнят', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $start = $this->daily->today();
        if ($dateOption = $input->getOption('date')) {
            $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateOption, new \DateTimeZone(DailyPickService::TIMEZONE));
            if (!$start || $start->format('Y-m-d') !== $dateOption) {
                $io->error('Невалидна дата. Използвайте формат ГГГГ-ММ-ДД, напр. 2026-11-20.');

                return Command::INVALID;
            }
        }

        $days = (int) $input->getOption('days');
        if ($days < 1 || $days > 60) {
            $io->error('--days трябва да е между 1 и 60.');

            return Command::INVALID;
        }

        $rows = [];
        for ($i = 0; $i < $days; ++$i) {
            $date = $start->modify("+$i days");
            foreach ($this->daily->ensureForDate($date) as $challenge) {
                $rows[] = [
                    $date->format('d.m.Y'),
                    $challenge->getPath()->getName(),
                    $challenge->getQuest()->getTitle(),
                    $challenge->isManual() ? 'на ръка' : 'автоматично',
                ];
            }
        }

        if (!$rows) {
            $io->warning('Няма публикувани куестове, отбелязани като „Може да бъде предизвикателство на деня“.');

            return Command::SUCCESS;
        }

        // From cron (non-interactive) stay silent on success: cPanel e-mails every line of output.
        if ($output->isVerbose() || $input->isInteractive()) {
            $io->table(['Ден', 'Пътека', 'Куест', 'Избор'], $rows);
            $io->success(\sprintf('Готово: %d предизвикателства.', \count($rows)));
        }

        return Command::SUCCESS;
    }
}
