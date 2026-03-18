<?php

declare(strict_types=1);

namespace App\Domain\Loan\Exception;

use App\Domain\Shared\DomainErrorCode;
use App\Domain\Shared\DomainException;

class LoanAlreadyPaidOffException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Loan with id "%s" is already paid off.', $id));
    }

    #[\Override]
    public function errorCode(): DomainErrorCode
    {
        return DomainErrorCode::LOAN_ALREADY_PAID_OFF;
    }
}
