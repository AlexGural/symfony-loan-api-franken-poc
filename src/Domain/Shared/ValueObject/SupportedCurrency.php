<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

enum SupportedCurrency: string
{
    case EUR = 'EUR';
    case USD = 'USD';

    public static function isSupported(string $code): bool
    {
        return null !== self::tryFrom(strtoupper($code));
    }

    /**
     * @return string[]
     *
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
