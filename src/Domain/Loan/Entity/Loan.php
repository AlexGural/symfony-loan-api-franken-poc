<?php

declare(strict_types=1);

namespace App\Domain\Loan\Entity;

use App\Domain\Loan\Exception\LoanAlreadyPaidOffException;
use App\Domain\Loan\Exception\PaymentCurrencyMismatchException;
use App\Domain\Loan\ValueObject\LoanId;
use App\Domain\Loan\ValueObject\LoanStatus;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\UserId;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Money\Currency;
use Brick\Money\Money;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'loans')]
#[ORM\Index(name: 'idx_loans_user_id', columns: ['user_id'])]
class Loan
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'bigint')]
    private string $principalAmount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $annualInterestRate;

    #[ORM\Column(type: 'integer')]
    private int $termMonths;

    #[ORM\Column(type: 'bigint')]
    private string $initialMonthlyPayment;

    #[ORM\Column(type: 'bigint')]
    private string $remainingBalance;

    #[ORM\Column(type: 'integer')]
    private int $paidMonths = 0;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        LoanId $id,
        User $user,
        Money $principal,
        BigDecimal $annualInterestRate,
        int $termMonths,
        Money $initialMonthlyPayment,
    ) {
        $this->id = $id->value;
        $this->user = $user;
        $this->principalAmount = (string) $principal->getMinorAmount();
        $this->currency = $principal->getCurrency()->getCurrencyCode();
        $this->annualInterestRate = (string) $annualInterestRate;
        $this->termMonths = $termMonths;
        $this->initialMonthlyPayment = (string) $initialMonthlyPayment->getMinorAmount();
        $this->remainingBalance = (string) $principal->getMinorAmount();
        $this->status = LoanStatus::ACTIVE->value;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function applyPayment(Money $amount): void
    {
        if (LoanStatus::PAID_OFF === $this->getStatus()) {
            throw LoanAlreadyPaidOffException::withId($this->id);
        }

        if ($amount->getCurrency()->getCurrencyCode() !== $this->currency) {
            throw PaymentCurrencyMismatchException::withCurrencies($amount->getCurrency()->getCurrencyCode(), $this->currency);
        }

        $remaining = BigInteger::of($this->remainingBalance);
        $paymentMinor = $amount->getMinorAmount()->toBigInteger();
        $newBalance = $remaining->minus($paymentMinor);

        if ($newBalance->isLessThanOrEqualTo(0)) {
            $newBalance = BigInteger::zero();
            $this->status = LoanStatus::PAID_OFF->value;
        }

        $this->remainingBalance = (string) $newBalance;
        ++$this->paidMonths;
    }

    public function getId(): LoanId
    {
        return new LoanId($this->id);
    }

    public function getUserId(): UserId
    {
        return $this->user->getId();
    }

    public function getPrincipal(): Money
    {
        return Money::ofMinor($this->principalAmount, $this->currency);
    }

    public function getCurrency(): Currency
    {
        return Currency::of($this->currency);
    }

    public function getAnnualInterestRate(): BigDecimal
    {
        return BigDecimal::of($this->annualInterestRate);
    }

    public function getTermMonths(): int
    {
        return $this->termMonths;
    }

    public function getInitialMonthlyPayment(): Money
    {
        return Money::ofMinor($this->initialMonthlyPayment, $this->currency);
    }

    public function getRemainingBalance(): Money
    {
        return Money::ofMinor($this->remainingBalance, $this->currency);
    }

    public function getPaidMonths(): int
    {
        return $this->paidMonths;
    }

    public function getStatus(): LoanStatus
    {
        return LoanStatus::from($this->status);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
