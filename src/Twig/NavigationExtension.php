<?php

namespace App\Twig;

use App\Entity\Path;
use App\Repository\PathRepository;
use Twig\Attribute\AsTwigFunction;

final class NavigationExtension
{
    /** @var list<Path>|null */
    private ?array $paths = null;

    public function __construct(private readonly PathRepository $repository)
    {
    }

    /** Paths for the main menu, in admin order (one query per request). @return list<Path> */
    #[AsTwigFunction('nav_paths')]
    public function navPaths(): array
    {
        return $this->paths ??= $this->repository->findAllOrdered();
    }
}
