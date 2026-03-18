<?php

declare(strict_types=1);

namespace App\Presentation\Response\Loan;

use App\Domain\Loan\Entity\Loan;
use App\Presentation\Response\ApiResponseInterface;
use OpenApi\Attributes as OA;

final readonly class LoanResponseDto implements ApiResponseInterface
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: 'b2c3d4e5-f6a7-8901-bcde-f12345678901')]
        public string $id,

        #[OA\Property(format: 'uuid', example: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890')]
        public string $userId,

        #[OA\Property(description: 'Principal in minor units (cents)', example: 500000)]
        public int $principalAmount,

        #[OA\Property(example: 'EUR')]
        public string $currency,

        #[OA\Property(description: 'Annual rate in %', example: 5.75)]
        public float $annualInterestRate,

        #[OA\Property(example: 36)]
        public int $termMonths,

        #[OA\Property(description: 'First monthly payment in minor units', example: 15106)]
        public int $initialMonthlyPayment,

        #[OA\Property(description: 'Remaining balance in minor units', example: 350000)]
        public int $remainingBalance,

        #[OA\Property(example: 6)]
        public int $paidMonths,

        #[OA\Property(enum: ['active', 'paid_off'], example: 'active')]
        public string $status,

        #[OA\Property(format: 'date-time', example: '2026-03-01T12:00:00+00:00')]
        public string $createdAt,
    ) {
    }

    #[\NoDiscard]
    public static function toResponse(Loan $loan): self
    {
        return new self(
            id: $loan->getId()->value,
            userId: $loan->getUserId()->value,
            principalAmount: $loan->getPrincipal()->getMinorAmount()->toInt(),
            currency: $loan->getCurrency()->getCurrencyCode(),
            annualInterestRate: $loan->getAnnualInterestRate()->toFloat(),
            termMonths: $loan->getTermMonths(),
            initialMonthlyPayment: $loan->getInitialMonthlyPayment()->getMinorAmount()->toInt(),
            remainingBalance: $loan->getRemainingBalance()->getMinorAmount()->toInt(),
            paidMonths: $loan->getPaidMonths(),
            status: $loan->getStatus()->value,
            createdAt: $loan->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
