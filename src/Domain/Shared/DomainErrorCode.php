<?php

declare(strict_types=1);

namespace App\Domain\Shared;

enum DomainErrorCode: int
{
    case USER_NOT_FOUND = 1001;
    case USER_ALREADY_EXISTS = 1002;

    case LOAN_NOT_FOUND = 2001;
    case LOAN_ALREADY_PAID_OFF = 2002;
    case PAYMENT_CURRENCY_MISMATCH = 2003;
    case MIN_PAYMENT_AMOUNT = 2004;

    case EXCHANGE_RATE_UNAVAILABLE = 3001;
    case UNSUPPORTED_CURRENCY = 3002;
}
