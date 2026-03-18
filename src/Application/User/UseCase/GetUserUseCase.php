<?php

declare(strict_types=1);

namespace App\Application\User\UseCase;

use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;

final readonly class GetUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    #[\NoDiscard]
    public function execute(string $id): User
    {
        return $this->userRepository->findById(new UserId($id))
            ?? throw UserNotFoundException::withId($id);
    }
}
