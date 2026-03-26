<?php

declare(strict_types=1);

namespace App\Application\Loan\UseCase;

use App\Domain\Loan\Exception\LoanNotFoundException;
use App\Domain\Loan\Repository\LoanRepositoryInterface;
use App\Domain\Loan\Service\LoanStrategy\PaymentStrategyResolver;
use App\Domain\Loan\ValueObject\LoanId;
use Brick\Money\Money;

final readonly class GetMinLoanPayment
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private PaymentStrategyResolver $paymentStrategyResolver,
    ) {
    }

    #[\NoDiscard]
    public function execute(string $loanId): Money
    {
        $loan = $this->loanRepository->findById(LoanId::fromString($loanId))
            ?? throw LoanNotFoundException::withId($loanId);

        $paymentStrategy = $this->paymentStrategyResolver->resolve($loan->getTermMonths());

        return $paymentStrategy->calculatePaymentAmount(
            $loan->getPrincipal(),
            $loan->getAnnualInterestRate(),
            $loan->getTermMonths(),
            $loan->getPaidMonths() + 1, // calculate next payment
            $loan->getRemainingBalance()
        );
    }
}
