<?php

declare(strict_types=1);

namespace App\Domain\Loan\ValueObject;

enum LoanStatus: string
{
    case ACTIVE = 'active';
    case PAID_OFF = 'paid_off';
}
