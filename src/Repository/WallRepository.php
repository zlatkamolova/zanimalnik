<?php

namespace App\Repository;

use App\Entity\Wall;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Wall> */
class WallRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wall::class);
    }

    public function findOneBySlug(string $slug): ?Wall
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
