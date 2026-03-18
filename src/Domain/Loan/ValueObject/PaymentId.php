<?php

declare(strict_types=1);

namespace App\Domain\Loan\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class PaymentId
{
    public function __construct(
        public string $value,
    ) {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid UUID: %s', $value));
        }
    }

    #[\NoDiscard]
    public static function generate(): self
    {
        return Uuid::v7()->toRfc4122() |> (fn (string $uuid) => new self($uuid));
    }
}
