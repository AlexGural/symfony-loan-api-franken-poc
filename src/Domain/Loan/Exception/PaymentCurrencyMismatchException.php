<?php

declare(strict_types=1);

namespace App\Domain\Loan\Exception;

use App\Domain\Shared\DomainErrorCode;
use App\Domain\Shared\DomainException;

final class PaymentCurrencyMismatchException extends DomainException
{
    public static function withCurrencies(string $paymentCurrency, string $loanCurrency): self
    {
        return new self(sprintf(
            'Payment currency %s does not match loan currency %s.',
            $paymentCurrency,
            $loanCurrency,
        ));
    }

    #[\Override]
    public function errorCode(): DomainErrorCode
    {
        return DomainErrorCode::PAYMENT_CURRENCY_MISMATCH;
    }
}
