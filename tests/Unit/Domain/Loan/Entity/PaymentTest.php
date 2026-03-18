<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Loan\Entity;

use App\Domain\Loan\Entity\Loan;
use App\Domain\Loan\Entity\Payment;
use App\Domain\Loan\ValueObject\LoanId;
use App\Domain\Loan\ValueObject\PaymentId;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    private function createLoan(): Loan
    {
        $user = new User(UserId::generate(), 'Test', new Email('test@test.com'));

        return new Loan(
            id: LoanId::generate(),
            user: $user,
            principal: Money::ofMinor(500000, 'EUR'),
            annualInterestRate: BigDecimal::of('6.00'),
            termMonths: 36,
            initialMonthlyPayment: Money::ofMinor(15211, 'EUR'),
        );
    }

    public function testBasicPaymentCreation(): void
    {
        $loan = $this->createLoan();
        $payment = new Payment(
            id: PaymentId::generate(),
            loan: $loan,
            amount: Money::ofMinor(15000, 'EUR'),
            remainingBalanceAfter: Money::ofMinor(485000, 'EUR'),
            paymentNumber: 1,
        );

        self::assertSame(15000, $payment->getAmount()->getMinorAmount()->toInt());
        self::assertSame('EUR', $payment->getAmount()->getCurrency()->getCurrencyCode());
        self::assertSame(485000, $payment->getRemainingBalanceAfter()->getMinorAmount()->toInt());
        self::assertSame(1, $payment->getPaymentNumber());
        self::assertInstanceOf(\DateTimeImmutable::class, $payment->getPaidAt());
    }

    public function testPaymentWithoutCrossCurrencyFieldsAreNull(): void
    {
        $loan = $this->createLoan();
        $payment = new Payment(
            id: PaymentId::generate(),
            loan: $loan,
            amount: Money::ofMinor(15000, 'EUR'),
            remainingBalanceAfter: Money::ofMinor(485000, 'EUR'),
            paymentNumber: 1,
        );

        self::assertNull($payment->getOriginalAmount());
        self::assertNull($payment->getExchangeRate());
    }

    public function testCrossCurrencyPayment(): void
    {
        $loan = $this->createLoan();
        $payment = new Payment(
            id: PaymentId::generate(),
            loan: $loan,
            amount: Money::ofMinor(9000, 'EUR'),
            remainingBalanceAfter: Money::ofMinor(491000, 'EUR'),
            paymentNumber: 1,
            originalAmount: Money::ofMinor(10000, 'USD'),
            exchangeRate: BigDecimal::of('0.900000'),
        );

        self::assertSame(9000, $payment->getAmount()->getMinorAmount()->toInt());
        self::assertSame('EUR', $payment->getAmount()->getCurrency()->getCurrencyCode());

        $original = $payment->getOriginalAmount();
        self::assertNotNull($original);
        self::assertSame(10000, $original->getMinorAmount()->toInt());
        self::assertSame('USD', $original->getCurrency()->getCurrencyCode());

        self::assertTrue($payment->getExchangeRate()->isEqualTo('0.900000'));
    }

    public function testLoanIdFromPayment(): void
    {
        $loan = $this->createLoan();
        $payment = new Payment(
            id: PaymentId::generate(),
            loan: $loan,
            amount: Money::ofMinor(15000, 'EUR'),
            remainingBalanceAfter: Money::ofMinor(485000, 'EUR'),
            paymentNumber: 1,
        );

        self::assertTrue($payment->getLoanId()->equals($loan->getId()));
    }
}
