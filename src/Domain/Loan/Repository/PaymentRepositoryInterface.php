<?php

declare(strict_types=1);

namespace App\Domain\Loan\Repository;

use App\Domain\Loan\Entity\Payment;
use App\Domain\Loan\ValueObject\LoanId;

interface PaymentRepositoryInterface
{
    public function persist(Payment $payment): void;

    public function save(Payment $payment): void;

    /** @return Payment[] */
    public function findByLoanId(LoanId $loanId): array;
}
