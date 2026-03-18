<?php

declare(strict_types=1);

namespace App\Domain\Loan\Service\ExchangeRate;

use App\Domain\Loan\ValueObject\ExchangeRate;
use App\Domain\Shared\ValueObject\SupportedCurrency;

interface ExchangeRateServiceInterface
{
    #[\NoDiscard]
    public function getBestRate(SupportedCurrency $from, SupportedCurrency $to): ExchangeRate;
}
