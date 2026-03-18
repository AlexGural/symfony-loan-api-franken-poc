<?php

declare(strict_types=1);

namespace App\Domain\Loan\ValueObject;

use App\Domain\Shared\ValueObject\SupportedCurrency;
use Brick\Math\BigDecimal;

final readonly class ExchangeRate
{
    public function __construct(
        public SupportedCurrency $from,
        public SupportedCurrency $to,
        public BigDecimal $rate,
        public string $provider,
    ) {
    }
}
