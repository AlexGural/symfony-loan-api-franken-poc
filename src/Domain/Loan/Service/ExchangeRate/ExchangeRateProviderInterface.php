<?php

declare(strict_types=1);

namespace App\Domain\Loan\Service\ExchangeRate;

use App\Domain\Loan\ValueObject\ExchangeRate;
use App\Domain\Shared\ValueObject\SupportedCurrency;

interface ExchangeRateProviderInterface
{
    #[\NoDiscard]
    public function getRate(SupportedCurrency $from, SupportedCurrency $to): ExchangeRate;
}
