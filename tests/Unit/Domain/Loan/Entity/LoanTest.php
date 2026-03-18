<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Loan\Entity;

use App\Domain\Loan\Entity\Loan;
use App\Domain\Loan\Exception\LoanAlreadyPaidOffException;
use App\Domain\Loan\Exception\PaymentCurrencyMismatchException;
use App\Domain\Loan\ValueObject\LoanId;
use App\Domain\Loan\ValueObject\LoanStatus;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

final class LoanTest extends TestCase
{
    private function createLoan(int $principalMinor = 500000): Loan
    {
        $user = new User(UserId::generate(), 'Test', new Email('test@test.com'));

        return new Loan(
            id: LoanId::generate(),
            user: $user,
            principal: Money::ofMinor($principalMinor, 'EUR'),
            annualInterestRate: BigDecimal::of('6.00'),
            termMonths: 36,
            initialMonthlyPayment: Money::ofMinor(15211, 'EUR'),
        );
    }

    public function testNewLoanIsActive(): void
    {
        $loan = $this->createLoan();

        self::assertSame(LoanStatus::ACTIVE, $loan->getStatus());
        self::assertSame(0, $loan->getPaidMonths());
    }

    public function testRemainingBalanceEqualsOriginalPrincipal(): void
    {
        $loan = $this->createLoan(500000);

        self::assertSame(500000, $loan->getRemainingBalance()->getMinorAmount()->toInt());
        self::assertSame(500000, $loan->getPrincipal()->getMinorAmount()->toInt());
    }

    public function testApplyPaymentReducesBalance(): void
    {
        $loan = $this->createLoan(500000);

        $loan->applyPayment(Money::ofMinor(15000, 'EUR'));

        self::assertSame(485000, $loan->getRemainingBalance()->getMinorAmount()->toInt());
        self::assertSame(1, $loan->getPaidMonths());
        self::assertSame(LoanStatus::ACTIVE, $loan->getStatus());
    }

    public function testPayOffLoan(): void
    {
        $loan = $this->createLoan(10000);

        $loan->applyPayment(Money::ofMinor(10000, 'EUR'));

        self::assertSame(0, $loan->getRemainingBalance()->getMinorAmount()->toInt());
        self::assertSame(LoanStatus::PAID_OFF, $loan->getStatus());
    }

    public function testOverpaymentResultsInZeroBalance(): void
    {
        $loan = $this->createLoan(10000);

        $loan->applyPayment(Money::ofMinor(15000, 'EUR'));

        self::assertSame(0, $loan->getRemainingBalance()->getMinorAmount()->toInt());
        self::assertSame(LoanStatus::PAID_OFF, $loan->getStatus());
    }

    public function testCannotPayAlreadyPaidOffLoan(): void
    {
        $loan = $this->createLoan(10000);
        $loan->applyPayment(Money::ofMinor(10000, 'EUR'));

        $this->expectException(LoanAlreadyPaidOffException::class);
        $loan->applyPayment(Money::ofMinor(1000, 'EUR'));
    }

    public function testCurrencyMismatchThrowsException(): void
    {
        $loan = $this->createLoan(500000);

        $this->expectException(PaymentCurrencyMismatchException::class);
        $this->expectExceptionMessage('Payment currency USD does not match loan currency EUR');

        $loan->applyPayment(Money::ofMinor(15000, 'USD'));
    }

    public function testGettersReturnCorrectValues(): void
    {
        $loan = $this->createLoan(500000);

        self::assertSame('EUR', $loan->getCurrency()->getCurrencyCode());
        self::assertTrue($loan->getAnnualInterestRate()->isEqualTo('6.00'));
        self::assertSame(36, $loan->getTermMonths());
        self::assertSame(15211, $loan->getInitialMonthlyPayment()->getMinorAmount()->toInt());
        self::assertInstanceOf(\DateTimeImmutable::class, $loan->getCreatedAt());
    }

    public function testMultiplePaymentsTrackPaidMonths(): void
    {
        $loan = $this->createLoan(100000);

        $loan->applyPayment(Money::ofMinor(10000, 'EUR'));
        $loan->applyPayment(Money::ofMinor(10000, 'EUR'));
        $loan->applyPayment(Money::ofMinor(10000, 'EUR'));

        self::assertSame(3, $loan->getPaidMonths());
        self::assertSame(70000, $loan->getRemainingBalance()->getMinorAmount()->toInt());
    }
}
