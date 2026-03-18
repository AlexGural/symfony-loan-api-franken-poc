<?php

declare(strict_types=1);

namespace App\Domain\Loan\Exception;

use App\Domain\Shared\DomainErrorCode;
use App\Domain\Shared\DomainException;

final class ExchangeRateUnavailableException extends DomainException
{
    public static function noProvidersResponded(): self
    {
        return new self('No exchange rate providers returned a valid rate.');
    }

    #[\Override]
    public function errorCode(): DomainErrorCode
    {
        return DomainErrorCode::EXCHANGE_RATE_UNAVAILABLE;
    }
}
