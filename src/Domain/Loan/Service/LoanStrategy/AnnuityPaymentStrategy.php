<?php

declare(strict_types=1);

namespace App\Domain\Loan\Service\LoanStrategy;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

/**
 * Used for loans with term > 24 months.
 */
final class AnnuityPaymentStrategy implements PaymentCalculationStrategyInterface
{
    private const int CALC_SCALE = 20;

    #[\Override]
    public function supports(int $termMonths): bool
    {
        return $termMonths > 24;
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
        $monthlyRate = $annualRate
            ->dividedBy(100, self::CALC_SCALE, RoundingMode::HALF_UP)
            ->dividedBy(12, self::CALC_SCALE, RoundingMode::HALF_UP);

        if ($monthlyRate->isZero()) {
            return $principal->dividedBy($termMonths, RoundingMode::HALF_UP);
        }

        $principalMinor = $principal->getMinorAmount()->toBigDecimal();

        $onePlusR = BigDecimal::one()->plus($monthlyRate);
        $pow = $this->power($onePlusR, $termMonths);

        $numerator = $principalMinor->multipliedBy(
            $monthlyRate->multipliedBy($pow),
        );
        $denominator = $pow->minus(BigDecimal::one());

        $paymentMinor = $numerator->dividedBy($denominator, 0, RoundingMode::HALF_UP);

        return Money::ofMinor($paymentMinor, $principal->getCurrency());
    }

    private function power(BigDecimal $base, int $exponent): BigDecimal
    {
        $result = BigDecimal::one();

        for ($i = 0; $i < $exponent; ++$i) {
            $result = $result->multipliedBy($base);
        }

        return $result;
    }
}
