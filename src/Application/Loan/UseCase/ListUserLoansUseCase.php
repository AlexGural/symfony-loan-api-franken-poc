<?php

declare(strict_types=1);

namespace App\Application\Loan\UseCase;

use App\Domain\Loan\Entity\Loan;
use App\Domain\Loan\Repository\LoanRepositoryInterface;
use App\Domain\User\ValueObject\UserId;

final readonly class ListUserLoansUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
    ) {
    }

    /** @return Loan[] */
    #[\NoDiscard]
    public function execute(string $userId): array
    {
        return $this->loanRepository->findByUserId(new UserId($userId));
    }
}
