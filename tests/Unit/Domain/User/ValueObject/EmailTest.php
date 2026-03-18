<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\ValueObject;

use App\Domain\User\ValueObject\Email;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testValidEmail(): void
    {
        $email = new Email('test@example.com');

        self::assertSame('test@example.com', $email->value);
    }

    public function testInvalidEmailThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Email('invalid');
    }

    public function testEquality(): void
    {
        $a = new Email('test@example.com');
        $b = new Email('test@example.com');

        self::assertTrue($a->equals($b));
    }
}
