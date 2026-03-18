<?php

declare(strict_types=1);

namespace App\Domain\Shared;

interface TransactionalRepositoryInterface
{
    /** @param callable(): mixed $operation */
    public function executeTransactional(callable $operation): mixed;
}
