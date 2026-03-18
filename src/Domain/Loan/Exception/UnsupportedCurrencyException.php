<?php

declare(strict_types=1);

namespace App\Domain\Loan\Exception;

use App\Domain\Shared\DomainErrorCode;
use App\Domain\Shared\DomainException;

final class UnsupportedCurrencyException extends DomainException
{
    public static function withCode(string $code): self
    {
        return new self(sprintf('Currency "%s" is not supported.', $code));
    }

    #[\Override]
    public function errorCode(): DomainErrorCode
    {
        return DomainErrorCode::UNSUPPORTED_CURRENCY;
    }
}
