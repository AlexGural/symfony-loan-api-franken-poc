<?php

declare(strict_types=1);

namespace App\Domain\Loan\Repository;

use App\Domain\Loan\Entity\Loan;
use App\Domain\Loan\ValueObject\LoanId;
use App\Domain\User\ValueObject\UserId;

interface LoanRepositoryInterface
{
    public function findById(LoanId $id): ?Loan;

    public function findByIdForUpdate(LoanId $id): ?Loan;

    /** @return Loan[] */
    public function findByUserId(UserId $userId): array;

    public function persist(Loan $loan): void;

    public function save(Loan $loan): void;
}
