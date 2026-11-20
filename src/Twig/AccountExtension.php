<?php

namespace App\Twig;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

final class AccountExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * The logged-in visitor for the layout. Never fails: the layout is also used by the error
     * pages, which must render even when the database is down.
     */
    #[AsTwigFunction('current_user')]
    public function currentUser(): ?User
    {
        try {
            $user = $this->security->getUser();
        } catch (\Throwable $e) {
            $this->logger->warning('Current user unavailable: {message}', ['message' => $e->getMessage()]);

            return null;
        }

        return $user instanceof User ? $user : null;
    }
}
