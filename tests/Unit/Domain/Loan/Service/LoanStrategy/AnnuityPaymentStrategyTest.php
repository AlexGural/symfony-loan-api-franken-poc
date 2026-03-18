<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Loan\Service\LoanStrategy;

use App\Domain\Loan\Service\LoanStrategy\AnnuityPaymentStrategy;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

final class AnnuityPaymentStrategyTest extends TestCase
{
    private AnnuityPaymentStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new AnnuityPaymentStrategy();
    }

    public function testStandardLoan36Months(): void
    {
        // €5,000 at 6% annual for 36 months
        // Expected monthly: M = 5000 * [0.005 * 1.005^36] / [1.005^36 - 1]
        // ≈ €152.11 → 15211 minor units
        $principal = Money::ofMinor(500000, 'EUR');
        $rate = BigDecimal::of('6.00');

        $payment = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 36, 1, $principal,
        );

        $minor = $payment->getMinorAmount()->toInt();
        self::assertEqualsWithDelta(15211, $minor, 2);
        self::assertSame('EUR', $payment->getCurrency()->getCurrencyCode());
    }

    public function testLargeLoan420Months(): void
    {
        // €500,000 at 7.5% for 420 months (35 years)
        // M = P*[r(1+r)^n]/[(1+r)^n-1], r=0.00625, n=420
        // ≈ €3,371.21 → 337121 minor
        $principal = Money::ofMinor(50000000, 'EUR');
        $rate = BigDecimal::of('7.50');

        $payment = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 420, 1, $principal,
        );

        $minor = $payment->getMinorAmount()->toInt();
        self::assertEqualsWithDelta(337121, $minor, 5);
    }

    public function testZeroInterestRate(): void
    {
        // €12,000 at 0% for 12 months → exactly €1,000/month
        $principal = Money::ofMinor(1200000, 'EUR');
        $rate = BigDecimal::of('0.00');

        $payment = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 12, 1, $principal,
        );

        self::assertSame(100000, $payment->getMinorAmount()->toInt());
    }

    public function testPaymentIsConsistentAcrossPaymentNumbers(): void
    {
        // Annuity payment should be the same regardless of payment number
        $principal = Money::ofMinor(1000000, 'EUR');
        $rate = BigDecimal::of('5.00');

        $first = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 48, 1, $principal,
        );
        $tenth = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 48, 10, Money::ofMinor(800000, 'EUR'),
        );

        self::assertSame(
            $first->getMinorAmount()->toInt(),
            $tenth->getMinorAmount()->toInt(),
        );
    }

    public function testPaymentIsAlwaysPositive(): void
    {
        $principal = Money::ofMinor(100000, 'EUR');
        $rate = BigDecimal::of('3.00');

        $payment = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 60, 1, $principal,
        );

        self::assertTrue($payment->getMinorAmount()->toInt() > 0);
    }

    public function testCurrencyPreserved(): void
    {
        $principal = Money::ofMinor(500000, 'USD');
        $rate = BigDecimal::of('5.00');

        $payment = $this->strategy->calculatePaymentAmount(
            $principal, $rate, 36, 1, $principal,
        );

        self::assertSame('USD', $payment->getCurrency()->getCurrencyCode());
    }
}
