<?php

declare(strict_types=1);

namespace App\Tests\Functional\Support;

use App\Domain\Loan\Service\ExchangeRate\ExchangeRateServiceInterface;
use App\Domain\Loan\ValueObject\ExchangeRate;
use App\Domain\Shared\ValueObject\SupportedCurrency;
use Brick\Math\BigDecimal;

final class StubExchangeRateService implements ExchangeRateServiceInterface
{
    public function getBestRate(SupportedCurrency $from, SupportedCurrency $to): ExchangeRate
    {
        return new ExchangeRate($from, $to, BigDecimal::of('0.90'), 'stub_provider');
    }
}
