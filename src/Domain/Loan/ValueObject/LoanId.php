<?php

declare(strict_types=1);

namespace App\Domain\Loan\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class LoanId
{
    private function __construct(
        public string $value,
    ) {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid UUID: %s', $value));
        }
    }

    #[\NoDiscard]
    public static function fromUuid(Uuid $uuid): self
    {
        return new self($uuid->toRfc4122());
    }

    #[\NoDiscard]
    public static function fromString(string $uuid): self
    {
        return new self($uuid);
    }

    #[\NoDiscard]
    public static function generate(): self
    {
        return self::fromUuid(Uuid::v7());
    }

    #[\NoDiscard('LoanId comparison result must be used')]
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
