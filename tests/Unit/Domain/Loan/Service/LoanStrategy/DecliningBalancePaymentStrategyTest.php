<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Loan\Service\LoanStrategy;

use App\Domain\Loan\Service\LoanStrategy\DecliningBalancePaymentStrategy;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

final class DecliningBalancePaymentStrategyTest extends TestCase
{
    private DecliningBalancePaymentStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new DecliningBalancePaymentStrategy();
    }

    public function testFirstPayment(): void
    {
        // €12,000 at 6% annual for 12 months
        // Principal part = 1200000 / 12 = 100000
        // Interest part  = 1200000 * (6/100/12) = 1200000 * 0.005 = 6000
        // Total = 106000 (€1,060.00)
        $principal = Money::ofMinor(1200000, 'EUR');
        $rate = BigDecimal::of('6.00');

        $payment = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 12, 1, $principal,
        );

        self::assertSame(106000, $payment->getMinorAmount()->toInt());
    }

    public function testPaymentDecreasesWithLowerBalance(): void
    {
        $principal = Money::ofMinor(1200000, 'EUR');
        $rate = BigDecimal::of('6.00');

        $firstPayment = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 12, 1, $principal,
        );

        // After first payment, remaining ≈ €11,000
        $remaining = Money::ofMinor(1100000, 'EUR');
        $secondPayment = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 12, 2, $remaining,
        );

        // Principal part stays the same (100000)
        // Interest part = 1100000 * 0.005 = 5500
        // Total = 105500
        self::assertSame(105500, $secondPayment->getMinorAmount()->toInt());
        self::assertTrue(
            $secondPayment->getMinorAmount()->toInt() < $firstPayment->getMinorAmount()->toInt(),
        );
    }

    public function testZeroInterestRate(): void
    {
        // 0% rate → only principal part
        $principal = Money::ofMinor(2400000, 'EUR');
        $rate = BigDecimal::of('0.00');

        $payment = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 24, 1, $principal,
        );

        // 2400000 / 24 = 100000 + 0 interest
        self::assertSame(100000, $payment->getMinorAmount()->toInt());
    }

    public function testLastPaymentHasMinimalInterest(): void
    {
        $principal = Money::ofMinor(1200000, 'EUR');
        $rate = BigDecimal::of('6.00');

        // Last month: remaining is roughly one month's principal
        $remaining = Money::ofMinor(100000, 'EUR');
        $payment = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 12, 12, $remaining,
        );

        // Principal = 100000, Interest = 100000 * 0.005 = 500
        self::assertSame(100500, $payment->getMinorAmount()->toInt());
    }

    public function testCurrencyPreserved(): void
    {
        $principal = Money::ofMinor(500000, 'USD');
        $rate = BigDecimal::of('5.00');

        $payment = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 12, 1, $principal,
        );

        self::assertSame('USD', $payment->getCurrency()->getCurrencyCode());
    }
}
