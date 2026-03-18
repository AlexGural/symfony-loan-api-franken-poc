<?php

declare(strict_types=1);

namespace App\Domain\Loan\Service;

use App\Domain\Loan\Entity\Loan;
use App\Domain\Loan\Entity\Payment;
use App\Domain\Loan\Service\LoanStrategy\PaymentStrategyResolver;
use App\Domain\Loan\ValueObject\ConvertedPayment;
use App\Domain\Loan\ValueObject\LoanId;
use App\Domain\Loan\ValueObject\PaymentId;
use App\Domain\User\Entity\User;
use Brick\Money\Money;

final readonly class LoanManager
{
    public function __construct(
        private LoanConditionsGenerator $conditionsGenerator,
        private PaymentStrategyResolver $strategyResolver,
    ) {
    }

    #[\NoDiscard]
    public function createLoan(User $user, Money $principal): Loan
    {
        $conditions = $this->conditionsGenerator->generate();
        $strategy = $this->strategyResolver->resolve($conditions->termMonths);

        $monthlyPayment = $strategy->calculatePaymentAmount(
            principal: $principal,
            annualRate: $conditions->annualRate,
            termMonths: $conditions->termMonths,
            paymentNumber: 1,
            remainingBalance: $principal,
        );

        return new Loan(
            id: LoanId::generate(),
            user: $user,
            principal: $principal,
            annualInterestRate: $conditions->annualRate,
            termMonths: $conditions->termMonths,
            initialMonthlyPayment: $monthlyPayment,
        );
    }

    #[\NoDiscard]
    public function applyPayment(Loan $loan, Money $paymentAmount, ?ConvertedPayment $conversion = null): Payment
    {
        $remaining = $loan->getRemainingBalance();

        if ($paymentAmount->isGreaterThan($remaining)) {
            $paymentAmount = $remaining;
        }

        $loan->applyPayment($paymentAmount);

        return new Payment(
            id: PaymentId::generate(),
            loan: $loan,
            amount: $paymentAmount,
            remainingBalanceAfter: $loan->getRemainingBalance(),
            paymentNumber: $loan->getPaidMonths(),
            originalAmount: $conversion?->originalAmount,
            exchangeRate: $conversion?->exchangeRate,
        );
    }
}
