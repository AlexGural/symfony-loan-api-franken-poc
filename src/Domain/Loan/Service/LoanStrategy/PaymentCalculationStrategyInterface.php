<?php

declare(strict_types=1);

namespace App\Domain\Loan\Service\LoanStrategy;

use Brick\Math\BigDecimal;
use Brick\Money\Money;

interface PaymentCalculationStrategyInterface
{
    public function supports(int $termMonths): bool;

    /**
     * @param Money      $principal        Initial loan amount
     * @param BigDecimal $annualRate       Annual rate (e.g. 5.50 = 5.50%)
     * @param int        $termMonths       Total term in months
     * @param int        $paymentNumber    Payment number (1-based)
     * @param Money      $remainingBalance Current balance before payment
     */
    #[\NoDiscard]
    public function calculatePaymentAmount(
        Money $principal,
        BigDecimal $annualRate,
        int $termMonths,
        int $paymentNumber,
        Money $remainingBalance,
    ): Money;
}
