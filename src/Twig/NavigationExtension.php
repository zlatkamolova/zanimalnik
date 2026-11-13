<?php

namespace App\Twig;

use App\Entity\Path;
use App\Repository\PathRepository;
use Psr\Log\LoggerInterface;
use Twig\Attribute\AsTwigFunction;

final class NavigationExtension
{
    /** @var list<Path>|null */
    private ?array $paths = null;

    public function __construct(
        private readonly PathRepository $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Paths for the main menu, in admin order (one query per request).
     * Never fails: the layout is also used by the error pages, which must render even when
     * the database is down (or, on the very first deploy, not migrated yet).
     *
     * @return list<Path>
     */
    #[AsTwigFunction('nav_paths')]
    public function navPaths(): array
    {
        if (null === $this->paths) {
            try {
                $this->paths = $this->repository->findAllOrdered();
            } catch (\Throwable $e) {
                $this->logger->warning('Menu paths unavailable: {message}', ['message' => $e->getMessage()]);
                $this->paths = [];
            }
        }

        return $this->paths;
    }
}
