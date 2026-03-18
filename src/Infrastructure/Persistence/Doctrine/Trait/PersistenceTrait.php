<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Trait;

use Doctrine\ORM\EntityManagerInterface;

/**
 * @property EntityManagerInterface $em
 */
trait PersistenceTrait
{
    public function persist(object $entity): void
    {
        $this->em->persist($entity);
    }

    public function save(object $entity): void
    {
        $this->em->persist($entity);
        $this->em->flush();
    }
}
