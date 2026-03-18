<?php

declare(strict_types=1);

namespace App\Domain\Loan\Service;

use App\Domain\Loan\ValueObject\LoanConditions;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class LoanConditionsGenerator
{
    /**
     * @param int[] $availableTerms
     */
    public function __construct(
        private array $availableTerms,
        private float $minRate,
        private float $maxRate,
    ) {
    }

    #[\NoDiscard]
    public function generate(): LoanConditions
    {
        $termMonths = $this->availableTerms[array_rand($this->availableTerms)];

        /** @psalm-suppress InvalidOperand */
        $rawRate = $this->minRate + (mt_rand() / mt_getrandmax()) * ($this->maxRate - $this->minRate);
        $annualRate = BigDecimal::of((string) round($rawRate, 2))
            ->toScale(2, RoundingMode::HALF_UP);

        return new LoanConditions(
            termMonths: $termMonths,
            annualRate: $annualRate,
        );
    }
}
