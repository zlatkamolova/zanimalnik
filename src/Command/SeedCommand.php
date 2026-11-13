<?php

namespace App\Command;

use App\Seed\ContentSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Run by app:post-deploy. Only acts on an empty database, so it is safe on every deploy. */
#[AsCommand(name: 'app:seed', description: 'Попълва празна база с началното съдържание (пътеки, роли, примерни куестове)')]
final class SeedCommand extends Command
{
    public function __construct(private readonly ContentSeeder $seeder)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $created = $this->seeder->seed();
        if (!$created) {
            $io->note('Базата вече има съдържание — нищо не е променено.');

            return Command::SUCCESS;
        }

        $io->success('Създадено: '.implode(', ', array_map(fn ($k, $v) => "$v $k", array_keys($created), $created)).'.');

        return Command::SUCCESS;
    }
}
