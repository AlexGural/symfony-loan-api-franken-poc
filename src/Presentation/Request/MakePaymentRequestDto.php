<?php

declare(strict_types=1);

namespace App\Presentation\Request;

use App\Domain\Shared\ValueObject\SupportedCurrency;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MakePaymentRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        #[OA\Property(description: 'Payment amount in minor units (cents)', example: 20000)]
        public int $amountMinor,

        #[Assert\NotBlank]
        #[Assert\Length(exactly: 3)]
        #[Assert\Choice(callback: [SupportedCurrency::class, 'values'])]
        #[OA\Property(description: 'ISO 4217 currency code', example: 'USD')]
        public string $currency,
    ) {
    }
}
