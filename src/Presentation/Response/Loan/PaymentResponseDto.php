<?php

declare(strict_types=1);

namespace App\Presentation\Response\Loan;

use App\Domain\Loan\Entity\Payment;
use App\Presentation\Response\ApiResponseInterface;
use OpenApi\Attributes as OA;

final readonly class PaymentResponseDto implements ApiResponseInterface
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: 'c3d4e5f6-a7b8-9012-cdef-123456789012')]
        public string $id,

        #[OA\Property(format: 'uuid', example: 'b2c3d4e5-f6a7-8901-bcde-f12345678901')]
        public string $loanId,

        #[OA\Property(description: 'Payment amount in loan currency (minor units)', example: 15106)]
        public int $amount,

        #[OA\Property(description: 'Loan currency', example: 'EUR')]
        public string $currency,

        #[OA\Property(description: 'Balance after payment in minor units', example: 334894)]
        public int $remainingBalanceAfter,

        #[OA\Property(example: 1)]
        public int $paymentNumber,

        #[OA\Property(description: 'Original amount in client currency', example: 17000, nullable: true)]
        public ?int $originalAmount,

        #[OA\Property(description: 'Client payment currency', example: 'USD', nullable: true)]
        public ?string $originalCurrency,

        #[OA\Property(description: 'Exchange rate used for conversion', example: 0.888600, nullable: true)]
        public ?float $exchangeRate,

        #[OA\Property(format: 'date-time', example: '2026-03-01T12:05:00+00:00')]
        public string $paidAt,
    ) {
    }

    #[\NoDiscard]
    public static function toResponse(Payment $payment): self
    {
        $original = $payment->getOriginalAmount();

        return new self(
            id: $payment->getId()->value,
            loanId: $payment->getLoanId()->value,
            amount: $payment->getAmount()->getMinorAmount()->toInt(),
            currency: $payment->getAmount()->getCurrency()->getCurrencyCode(),
            remainingBalanceAfter: $payment->getRemainingBalanceAfter()->getMinorAmount()->toInt(),
            paymentNumber: $payment->getPaymentNumber(),
            originalAmount: $original?->getMinorAmount()->toInt(),
            originalCurrency: $original?->getCurrency()->getCurrencyCode(),
            exchangeRate: $payment->getExchangeRate()?->toFloat(),
            paidAt: $payment->getPaidAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
