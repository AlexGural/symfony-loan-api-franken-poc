<?php

declare(strict_types=1);

namespace App\Domain\Loan\Exception;

use App\Domain\Shared\DomainErrorCode;
use App\Domain\Shared\DomainException;

final class MinPaymentAmountException extends DomainException
{
    public static function withAmounts(int $paymentAmount, int $minAmount): self
    {
        return new self(sprintf(
            'Payment amount %s less that minimal required amount %s.',
            $paymentAmount,
            $minAmount,
        ));
    }

    #[\Override]
    public function errorCode(): DomainErrorCode
    {
        return DomainErrorCode::MIN_PAYMENT_AMOUNT;
    }
}
