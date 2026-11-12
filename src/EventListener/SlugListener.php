<?php

namespace App\EventListener;

use App\Entity\SluggableInterface;
use App\Service\Slugger;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/** Fills an empty slug from the title/name and makes it unique (-2, -3 …). */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class SlugListener
{
    public function __construct(private readonly Slugger $slugger)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof SluggableInterface && !$entity->getSlug()) {
            $entity->setSlug($this->uniqueSlug($args->getObjectManager(), $entity));
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof SluggableInterface && !$entity->getSlug()) {
            $args->setNewValue('slug', $this->uniqueSlug($args->getObjectManager(), $entity));
        }
    }

    private function uniqueSlug(EntityManagerInterface $em, SluggableInterface $entity): string
    {
        $base = $this->slugger->slug($entity->getSlugSource());
        $repository = $em->getRepository($entity::class);

        $slug = $base;
        for ($i = 2; ($existing = $repository->findOneBy(['slug' => $slug])) && $existing !== $entity; ++$i) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
