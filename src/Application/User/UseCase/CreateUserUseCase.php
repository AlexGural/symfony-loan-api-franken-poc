<?php

declare(strict_types=1);

namespace App\Application\User\UseCase;

use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use Psr\Log\LoggerInterface;

final readonly class CreateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private LoggerInterface $logger,
    ) {
    }

    #[\NoDiscard]
    public function execute(string $name, string $email): User
    {
        $emailVo = new Email($email);

        null === $this->userRepository->findByEmail($emailVo)
            ?: throw UserAlreadyExistsException::withEmail($email);

        $user = new User(
            id: UserId::generate(),
            name: $name,
            email: $emailVo,
        );

        $this->userRepository->save($user);

        $this->logger->info('user_created', [
            'userId' => $user->getId()->value,
        ]);

        return $user;
    }
}
