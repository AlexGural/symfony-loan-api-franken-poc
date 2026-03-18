<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\ValueObject;

use App\Domain\User\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserIdTest extends TestCase
{
    public function testGenerate(): void
    {
        $id = UserId::generate();

        self::assertNotEmpty($id->value);
    }

    public function testInvalidUuidThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UserId('not-a-uuid');
    }

    public function testEquality(): void
    {
        $uuid = '01942a6c-0b4a-7d6e-8a12-abcdef123456';
        $a = new UserId($uuid);
        $b = new UserId($uuid);

        self::assertTrue($a->equals($b));
    }
}
