<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * "Forgotten password": a random token is e-mailed as a link; only its sha256 is stored.
 * The link works once and for one hour. Asking for a new link cancels the previous one.
 */
final class PasswordResetService
{
    public const LIFETIME = '+1 hour';

    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')] private readonly string $from,
    ) {
    }

    /** Silent when there is no such account, so the form never reveals who is registered. */
    public function sendLink(string $email): void
    {
        $user = $this->users->findOneBy(['email' => mb_strtolower(trim($email))]);
        if (!$user) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $user->setResetToken(hash('sha256', $token), new \DateTimeImmutable(self::LIFETIME));
        $this->em->flush();

        $message = (new TemplatedEmail())
            ->from(new Address($this->from, 'Занималник'))
            ->to($user->getEmail())
            ->subject('Нова парола за Занималника')
            ->textTemplate('email/password_reset.txt.twig')
            ->htmlTemplate('email/password_reset.html.twig')
            ->context(['url' => $this->urls->generate('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL)]);

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Password reset e-mail could not be sent: {message}', ['message' => $e->getMessage()]);
        }
    }

    public function findUser(string $token): ?User
    {
        $user = $this->users->findOneBy(['resetTokenHash' => hash('sha256', $token)]);

        return $user && $user->getResetTokenExpiresAt() > new \DateTimeImmutable() ? $user : null;
    }

    public function changePassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $user->setResetToken(null, null);
        $this->em->flush();
    }
}
