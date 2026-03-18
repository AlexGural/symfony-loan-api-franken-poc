<?php

declare(strict_types=1);

namespace App\Application\User\UseCase;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;

final readonly class ListUsersUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /** @return User[] */
    #[\NoDiscard]
    public function execute(): array
    {
        return $this->userRepository->findAll();
    }
}
