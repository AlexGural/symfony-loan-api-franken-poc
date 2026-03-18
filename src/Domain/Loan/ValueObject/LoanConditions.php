<?php

declare(strict_types=1);

namespace App\Domain\Loan\ValueObject;

use Brick\Math\BigDecimal;

final readonly class LoanConditions
{
    public function __construct(
        public int $termMonths,
        public BigDecimal $annualRate,
    ) {
    }
}
