<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Shared\ValueObject;

use App\Domain\Shared\ValueObject\SupportedCurrency;
use PHPUnit\Framework\TestCase;

final class SupportedCurrencyTest extends TestCase
{
    public function testIsSupportedForValidCurrencies(): void
    {
        self::assertTrue(SupportedCurrency::isSupported('EUR'));
        self::assertTrue(SupportedCurrency::isSupported('USD'));
    }

    public function testIsSupportedCaseInsensitive(): void
    {
        self::assertTrue(SupportedCurrency::isSupported('eur'));
        self::assertTrue(SupportedCurrency::isSupported('usd'));
        self::assertTrue(SupportedCurrency::isSupported('Eur'));
    }

    public function testIsNotSupportedForUnknownCurrencies(): void
    {
        self::assertFalse(SupportedCurrency::isSupported('GBP'));
        self::assertFalse(SupportedCurrency::isSupported('JPY'));
        self::assertFalse(SupportedCurrency::isSupported(''));
        self::assertFalse(SupportedCurrency::isSupported('INVALID'));
    }

    public function testValuesReturnsAllCurrencyCodes(): void
    {
        $values = SupportedCurrency::values();

        self::assertContains('EUR', $values);
        self::assertContains('USD', $values);
        self::assertCount(2, $values);
    }

    public function testEnumValues(): void
    {
        self::assertSame('EUR', SupportedCurrency::EUR->value);
        self::assertSame('USD', SupportedCurrency::USD->value);
    }

    public function testFromString(): void
    {
        self::assertSame(SupportedCurrency::EUR, SupportedCurrency::from('EUR'));
        self::assertSame(SupportedCurrency::USD, SupportedCurrency::from('USD'));
    }

    public function testFromInvalidStringThrows(): void
    {
        $this->expectException(\ValueError::class);
        SupportedCurrency::from('GBP');
    }
}
