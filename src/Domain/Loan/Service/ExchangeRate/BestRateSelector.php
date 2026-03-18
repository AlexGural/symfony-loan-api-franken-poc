<?php

declare(strict_types=1);

namespace App\Domain\Loan\Service\ExchangeRate;

use App\Domain\Loan\Exception\ExchangeRateUnavailableException;
use App\Domain\Loan\ValueObject\ExchangeRate;

final readonly class BestRateSelector
{
    /**
     * @param ExchangeRate[] $rates
     */
    #[\NoDiscard]
    public function select(array $rates): ExchangeRate
    {
        if ([] === $rates) {
            throw ExchangeRateUnavailableException::noProvidersResponded();
        }

        $best = null;

        foreach ($rates as $rate) {
            if (null === $best || $rate->rate->isGreaterThan($best->rate)) {
                $best = $rate;
            }
        }

        return $best;
    }
}
