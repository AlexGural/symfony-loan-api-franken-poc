<?php

declare(strict_types=1);

namespace App\Application\Loan\UseCase;

use App\Domain\Loan\Entity\Payment;
use App\Domain\Loan\Exception\LoanNotFoundException;
use App\Domain\Loan\Repository\LoanRepositoryInterface;
use App\Domain\Loan\Repository\PaymentRepositoryInterface;
use App\Domain\Loan\ValueObject\LoanId;

final readonly class ListLoanPaymentsUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private PaymentRepositoryInterface $paymentRepository,
    ) {
    }

    /** @return Payment[] */
    #[\NoDiscard]
    public function execute(string $loanId): array
    {
        $id = LoanId::fromString($loanId);

        $this->loanRepository->findById($id)
            ?? throw LoanNotFoundException::withId($loanId);

        return $this->paymentRepository->findByLoanId($id);
    }
}
