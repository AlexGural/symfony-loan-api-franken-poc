<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Loan\Entity\Loan;
use App\Domain\Loan\Repository\LoanRepositoryInterface;
use App\Domain\Loan\ValueObject\LoanId;
use App\Domain\Shared\TransactionalRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\Doctrine\Trait\PersistenceTrait;
use App\Infrastructure\Persistence\Doctrine\Trait\TransactionalTrait;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLoanRepository implements LoanRepositoryInterface, TransactionalRepositoryInterface
{
    use PersistenceTrait;
    use TransactionalTrait;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function findById(LoanId $id): ?Loan
    {
        return $this->em->find(Loan::class, $id->value);
    }

    #[\Override]
    public function findByIdForUpdate(LoanId $id): ?Loan
    {
        return $this->em->find(Loan::class, $id->value, LockMode::PESSIMISTIC_WRITE);
    }

    /** @return Loan[] */
    #[\Override]
    public function findByUserId(UserId $userId): array
    {
        return $this->em->getRepository(Loan::class)->findBy(
            ['user' => $userId->value],
            ['createdAt' => 'DESC'],
        );
    }
}
