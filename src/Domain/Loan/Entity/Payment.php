<?php

declare(strict_types=1);

namespace App\Domain\Loan\Entity;

use App\Domain\Loan\ValueObject\LoanId;
use App\Domain\Loan\ValueObject\PaymentId;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payments')]
#[ORM\Index(name: 'idx_payments_loan_id', columns: ['loan_id'])]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Loan::class)]
    #[ORM\JoinColumn(name: 'loan_id', nullable: false, onDelete: 'CASCADE')]
    private Loan $loan;

    #[ORM\Column(type: 'bigint')]
    private string $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: 'bigint')]
    private string $remainingBalanceAfter;

    #[ORM\Column(type: 'integer')]
    private int $paymentNumber;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $originalAmount = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $originalCurrency = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 6, nullable: true)]
    private ?string $exchangeRate = null;

    #[ORM\Column]
    private \DateTimeImmutable $paidAt;

    public function __construct(
        PaymentId $id,
        Loan $loan,
        Money $amount,
        Money $remainingBalanceAfter,
        int $paymentNumber,
        ?Money $originalAmount = null,
        ?BigDecimal $exchangeRate = null,
    ) {
        $this->id = $id->value;
        $this->loan = $loan;
        $this->amount = (string) $amount->getMinorAmount();
        $this->currency = $amount->getCurrency()->getCurrencyCode();
        $this->remainingBalanceAfter = (string) $remainingBalanceAfter->getMinorAmount();
        $this->paymentNumber = $paymentNumber;
        $this->paidAt = new \DateTimeImmutable();

        if (null !== $originalAmount) {
            $this->originalAmount = (string) $originalAmount->getMinorAmount();
            $this->originalCurrency = $originalAmount->getCurrency()->getCurrencyCode();
        }

        if (null !== $exchangeRate) {
            $this->exchangeRate = (string) $exchangeRate;
        }
    }

    public function getId(): PaymentId
    {
        return new PaymentId($this->id);
    }

    public function getLoanId(): LoanId
    {
        return $this->loan->getId();
    }

    public function getAmount(): Money
    {
        return Money::ofMinor($this->amount, $this->currency);
    }

    public function getRemainingBalanceAfter(): Money
    {
        return Money::ofMinor($this->remainingBalanceAfter, $this->currency);
    }

    public function getPaymentNumber(): int
    {
        return $this->paymentNumber;
    }

    public function getOriginalAmount(): ?Money
    {
        if (null === $this->originalAmount || null === $this->originalCurrency) {
            return null;
        }

        return Money::ofMinor($this->originalAmount, $this->originalCurrency);
    }

    public function getExchangeRate(): ?BigDecimal
    {
        return null !== $this->exchangeRate ? BigDecimal::of($this->exchangeRate) : null;
    }

    public function getPaidAt(): \DateTimeImmutable
    {
        return $this->paidAt;
    }
}
