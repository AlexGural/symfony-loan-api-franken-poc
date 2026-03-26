<?php

declare(strict_types=1);

namespace App\Application\Loan\UseCase;

use App\Domain\Loan\Entity\Loan;
use App\Domain\Loan\Exception\LoanNotFoundException;
use App\Domain\Loan\Repository\LoanRepositoryInterface;
use App\Domain\Loan\ValueObject\LoanId;

final readonly class GetLoanUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
    ) {
    }

    #[\NoDiscard]
    public function execute(string $loanId): Loan
    {
        return $this->loanRepository->findById(LoanId::fromString($loanId))
            ?? throw LoanNotFoundException::withId($loanId);
    }
}
