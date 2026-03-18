<?php

declare(strict_types=1);

namespace App\Domain\Loan\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class LoanId
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

    #[\NoDiscard('LoanId comparison result must be used')]
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
