<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Shared\DomainErrorCode;
use App\Domain\Shared\DomainException;

class UserAlreadyExistsException extends DomainException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf('User with email "%s" already exists.', $email));
    }

    #[\Override]
    public function errorCode(): DomainErrorCode
    {
        return DomainErrorCode::USER_ALREADY_EXISTS;
    }
}
