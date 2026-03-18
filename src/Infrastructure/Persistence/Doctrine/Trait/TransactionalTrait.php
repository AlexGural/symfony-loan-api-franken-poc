<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Trait;

use Doctrine\ORM\EntityManagerInterface;

/**
 * @property EntityManagerInterface $em
 */
trait TransactionalTrait
{
    public function executeTransactional(callable $operation): mixed
    {
        return $this->em->wrapInTransaction($operation);
    }
}
