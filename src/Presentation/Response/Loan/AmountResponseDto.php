<?php

declare(strict_types=1);

namespace App\Presentation\Response\Loan;

use App\Presentation\Response\ApiResponseInterface;
use Brick\Math\Exception\MathException;
use Brick\Money\Money;
use OpenApi\Attributes as OA;

final readonly class AmountResponseDto implements ApiResponseInterface
{
    public function __construct(
        #[OA\Property(example: 'EUR')]
        public string $currency,

        #[OA\Property(description: 'Minimum payment in minor units', example: 500000)]
        public int $minPaymentAmountMinor,
    ) {
    }

    /**
     * @throws MathException
     */
    #[\NoDiscard]
    public static function toResponse(Money $amount): self
    {
        return new self(
            currency: $amount->getCurrency()->getCurrencyCode(),
            minPaymentAmountMinor: $amount->getMinorAmount()->toInt()
        );
    }
}
