<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\Shared\DomainErrorCode;
use App\Domain\Shared\DomainException;

class UserNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('User with id "%s" was not found.', $id));
    }

    public static function withEmail(string $email): self
    {
        return new self(sprintf('User with email "%s" was not found.', $email));
    }

    #[\Override]
    public function errorCode(): DomainErrorCode
    {
        return DomainErrorCode::USER_NOT_FOUND;
    }
}
