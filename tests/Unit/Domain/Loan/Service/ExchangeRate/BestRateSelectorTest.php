<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Loan\Service\ExchangeRate;

use App\Domain\Loan\Exception\ExchangeRateUnavailableException;
use App\Domain\Loan\Service\ExchangeRate\BestRateSelector;
use App\Domain\Loan\ValueObject\ExchangeRate;
use App\Domain\Shared\ValueObject\SupportedCurrency;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

final class BestRateSelectorTest extends TestCase
{
    private BestRateSelector $selector;

    protected function setUp(): void
    {
        $this->selector = new BestRateSelector();
    }

    public function testSelectsHighestRateForCompany(): void
    {
        $rates = [
            new ExchangeRate(SupportedCurrency::USD, SupportedCurrency::EUR, BigDecimal::of('0.92'), 'provider_a'),
            new ExchangeRate(SupportedCurrency::USD, SupportedCurrency::EUR, BigDecimal::of('0.88'), 'provider_b'),
            new ExchangeRate(SupportedCurrency::USD, SupportedCurrency::EUR, BigDecimal::of('0.90'), 'provider_c'),
        ];

        $best = $this->selector->select($rates);

        self::assertSame('provider_a', $best->provider);
        self::assertTrue($best->rate->isEqualTo(BigDecimal::of('0.92')));
    }

    public function testSingleRate(): void
    {
        $rate = new ExchangeRate(SupportedCurrency::USD, SupportedCurrency::EUR, BigDecimal::of('0.91'), 'only_one');

        $best = $this->selector->select([$rate]);

        self::assertSame('only_one', $best->provider);
    }

    public function testEmptyArrayThrowsException(): void
    {
        $this->expectException(ExchangeRateUnavailableException::class);

        (void) $this->selector->select([]);
    }

    public function testEqualRatesReturnsFirst(): void
    {
        $rates = [
            new ExchangeRate(SupportedCurrency::USD, SupportedCurrency::EUR, BigDecimal::of('0.90'), 'first'),
            new ExchangeRate(SupportedCurrency::USD, SupportedCurrency::EUR, BigDecimal::of('0.90'), 'second'),
        ];

        $best = $this->selector->select($rates);

        self::assertSame('first', $best->provider);
    }
}
