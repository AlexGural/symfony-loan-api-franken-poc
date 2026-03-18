<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Loan\Service;

use App\Domain\Loan\Service\LoanConditionsGenerator;
use App\Domain\Loan\Service\LoanManager;
use App\Domain\Loan\Service\LoanStrategy\AnnuityPaymentStrategy;
use App\Domain\Loan\Service\LoanStrategy\DecliningBalancePaymentStrategy;
use App\Domain\Loan\Service\LoanStrategy\PaymentStrategyResolver;
use App\Domain\Loan\ValueObject\ConvertedPayment;
use App\Domain\Loan\ValueObject\LoanStatus;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use PHPUnit\Framework\TestCase;

final class LoanManagerTest extends TestCase
{
    private LoanManager $manager;

    protected function setUp(): void
    {
        $conditionsGenerator = new LoanConditionsGenerator([36], 5.0, 5.0);
        $strategyResolver = new PaymentStrategyResolver([
            new DecliningBalancePaymentStrategy(),
            new AnnuityPaymentStrategy(),
        ]);

        $this->manager = new LoanManager($conditionsGenerator, $strategyResolver);
    }

    private function createTestUser(): User
    {
        return new User(UserId::generate(), 'Test User', new Email('test@example.com'));
    }

    public function testCreateLoanReturnsActiveLoan(): void
    {
        $principal = Money::ofMinor(500000, 'EUR');
        $loan = $this->manager->createLoan($this->createTestUser(), $principal);

        self::assertSame(LoanStatus::ACTIVE, $loan->getStatus());
        self::assertSame(36, $loan->getTermMonths());
        self::assertSame(0, $loan->getPaidMonths());
        self::assertSame(500000, $loan->getRemainingBalance()->getMinorAmount()->toInt());
        self::assertSame(500000, $loan->getPrincipal()->getMinorAmount()->toInt());
        self::assertTrue($loan->getAnnualInterestRate()->isEqualTo('5.00'));
    }

    public function testCreateLoanCalculatesMonthlyPayment(): void
    {
        $principal = Money::ofMinor(500000, 'EUR');
        $loan = $this->manager->createLoan($this->createTestUser(), $principal);

        self::assertTrue($loan->getInitialMonthlyPayment()->getMinorAmount()->toInt() > 0);
    }

    public function testApplyPaymentReducesBalance(): void
    {
        $principal = Money::ofMinor(500000, 'EUR');
        $loan = $this->manager->createLoan($this->createTestUser(), $principal);

        (void) $this->manager->applyPayment($loan, Money::ofMinor(15000, 'EUR'));

        self::assertSame(1, $loan->getPaidMonths());
        self::assertSame(485000, $loan->getRemainingBalance()->getMinorAmount()->toInt());
    }

    public function testApplyPaymentWithConversion(): void
    {
        $principal = Money::ofMinor(500000, 'EUR');
        $loan = $this->manager->createLoan($this->createTestUser(), $principal);

        $conversion = new ConvertedPayment(
            convertedAmount: Money::ofMinor(9000, 'EUR'),
            originalAmount: Money::ofMinor(10000, 'USD'),
            exchangeRate: BigDecimal::of('0.90'),
        );

        $payment = $this->manager->applyPayment($loan, $conversion->convertedAmount, $conversion);
        self::assertSame(9000, $payment->getAmount()->getMinorAmount()->toInt());
        self::assertSame('EUR', $payment->getAmount()->getCurrency()->getCurrencyCode());
        self::assertSame(10000, $payment->getOriginalAmount()->getMinorAmount()->toInt());
        self::assertSame('USD', $payment->getOriginalAmount()->getCurrency()->getCurrencyCode());
        self::assertTrue($payment->getExchangeRate()->isEqualTo('0.90'));
    }

    public function testApplyPaymentSameCurrencyNoConversion(): void
    {
        $principal = Money::ofMinor(500000, 'EUR');
        $loan = $this->manager->createLoan($this->createTestUser(), $principal);

        $payment = $this->manager->applyPayment($loan, Money::ofMinor(15000, 'EUR'));

        self::assertNull($payment->getOriginalAmount());
        self::assertNull($payment->getExchangeRate());
        self::assertSame(15000, $payment->getAmount()->getMinorAmount()->toInt());
    }

    public function testApplyPaymentCappedAtRemainingBalance(): void
    {
        $principal = Money::ofMinor(10000, 'EUR');
        $loan = $this->manager->createLoan($this->createTestUser(), $principal);

        $payment = $this->manager->applyPayment($loan, Money::ofMinor(99999, 'EUR'));

        self::assertSame(10000, $payment->getAmount()->getMinorAmount()->toInt());
        self::assertSame(0, $loan->getRemainingBalance()->getMinorAmount()->toInt());
        self::assertSame(LoanStatus::PAID_OFF, $loan->getStatus());
    }

    public function testMultiplePaymentsReduceBalanceToZero(): void
    {
        $conditionsGenerator = new LoanConditionsGenerator([12], 0.0, 0.0);
        $manager = new LoanManager(
            $conditionsGenerator,
            new PaymentStrategyResolver([
                new DecliningBalancePaymentStrategy(),
                new AnnuityPaymentStrategy(),
            ]),
        );

        $principal = Money::ofMinor(120000, 'EUR');
        $loan = $manager->createLoan($this->createTestUser(), $principal);

        for ($i = 0; $i < 12; ++$i) {
            if (LoanStatus::PAID_OFF === $loan->getStatus()) {
                break;
            }
            $remaining = $loan->getRemainingBalance()->getMinorAmount()->toInt();
            $payAmount = min(10000, $remaining);
            (void) $manager->applyPayment($loan, Money::ofMinor($payAmount, 'EUR'));
        }

        self::assertSame(LoanStatus::PAID_OFF, $loan->getStatus());
        self::assertSame(0, $loan->getRemainingBalance()->getMinorAmount()->toInt());
    }
}
