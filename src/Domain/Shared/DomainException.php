<?php

declare(strict_types=1);

namespace App\Domain\Shared;

abstract class DomainException extends \DomainException
{
    abstract public function errorCode(): DomainErrorCode;
}
