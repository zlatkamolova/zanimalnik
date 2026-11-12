<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Creates an administrator.
 *
 * Locally:   make console c="app:create-admin you@example.com"   (asks for the password)
 * On cPanel: put ADMIN_EMAIL and ADMIN_PASSWORD in .env.local and run the command once from cron
 *            without arguments. When the user already exists it does nothing (safe to repeat).
 */
#[AsCommand(name: 'app:create-admin', description: 'Създава администратор (имейл и парола от аргументи или от ADMIN_EMAIL / ADMIN_PASSWORD)')]
final class CreateAdminCommand extends Command
{
    private const MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Имейл (по подразбиране ADMIN_EMAIL)')
            ->addOption('reset-password', null, InputOption::VALUE_NONE, 'Ако потребителят съществува, сменя паролата му');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = mb_strtolower(trim((string) ($input->getArgument('email') ?: $this->env('ADMIN_EMAIL'))));
        if ('' === $email || \count($this->validator->validate($email, new Assert\Email()))) {
            $io->error('Липсва валиден имейл. Подайте го като аргумент или задайте ADMIN_EMAIL в .env.local.');

            return Command::INVALID;
        }

        $existing = $this->users->findOneBy(['email' => $email]);
        if ($existing && !$input->getOption('reset-password')) {
            $io->note(\sprintf('Администратор с имейл %s вече съществува. Нищо не е променено.', $email));

            return Command::SUCCESS;
        }

        $password = (string) $this->env('ADMIN_PASSWORD');
        if ('' === $password && $input->isInteractive()) {
            $password = (string) $io->askHidden('Парола (поне '.self::MIN_PASSWORD_LENGTH.' знака)');
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $io->error(\sprintf('Паролата трябва да е поне %d знака. Задайте ADMIN_PASSWORD в .env.local или я въведете.', self::MIN_PASSWORD_LENGTH));

            return Command::INVALID;
        }

        $user = $existing ?? (new User())->setEmail($email);
        $user->setRoles([User::ROLE_ADMIN]);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success($existing
            ? \sprintf('Паролата на %s е сменена.', $email)
            : \sprintf('Администраторът %s е създаден. Вход: /admin', $email));

        if ($this->env('ADMIN_PASSWORD')) {
            $io->warning('Изтрийте ADMIN_EMAIL и ADMIN_PASSWORD от .env.local и спрете еднократната cron задача.');
        }

        return Command::SUCCESS;
    }

    private function env(string $name): ?string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
