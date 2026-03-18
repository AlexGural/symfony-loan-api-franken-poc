<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Doctrine\Trait\PersistenceTrait;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserRepository implements UserRepositoryInterface
{
    use PersistenceTrait;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function findById(UserId $id): ?User
    {
        return $this->em->find(User::class, $id->value);
    }

    #[\Override]
    public function findByEmail(Email $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy([
            'email' => $email->value,
        ]);
    }

    #[\Override]
    public function remove(User $user): void
    {
        $this->em->remove($user);
        $this->em->flush();
    }

    /** @return User[] */
    #[\Override]
    public function findAll(): array
    {
        return $this->em->getRepository(User::class)->findAll();
    }
}
