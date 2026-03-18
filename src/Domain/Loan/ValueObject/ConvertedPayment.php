<?php

declare(strict_types=1);

namespace App\Domain\Loan\ValueObject;

use Brick\Math\BigDecimal;
use Brick\Money\Money;

final readonly class ConvertedPayment
{
    public function __construct(
        public Money $convertedAmount,
        public Money $originalAmount,
        public BigDecimal $exchangeRate,
    ) {
    }
}
