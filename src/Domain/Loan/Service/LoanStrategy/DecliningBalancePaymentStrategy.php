<?php

declare(strict_types=1);

namespace App\Domain\Loan\Service\LoanStrategy;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Used for loans with term <= 24 months.
 */
final class DecliningBalancePaymentStrategy implements PaymentCalculationStrategyInterface
{
    private const int CALC_SCALE = 20;

    #[\Override]
    public function supports(int $termMonths): bool
    {
        return $termMonths <= 24;
    }

    #[\NoDiscard]
    #[\Override]
    public function calculatePaymentAmount(
        Money $principal,
        BigDecimal $annualRate,
        int $termMonths,
        int $paymentNumber,
        Money $remainingBalance,
    ): Money {
        $principalMinor = $principal->getMinorAmount()->toBigDecimal();
        $remainingMinor = $remainingBalance->getMinorAmount()->toBigDecimal();

        $principalPart = $principalMinor->dividedBy($termMonths, 0, RoundingMode::HALF_UP);

        $monthlyRate = $annualRate
            ->dividedBy(100, self::CALC_SCALE, RoundingMode::HALF_UP)
            ->dividedBy(12, self::CALC_SCALE, RoundingMode::HALF_UP);

        $interestPart = $remainingMinor
            ->multipliedBy($monthlyRate)
            ->toScale(0, RoundingMode::HALF_UP);

        $total = $principalPart->plus($interestPart);

        return Money::ofMinor($total, $principal->getCurrency());
    }
}
