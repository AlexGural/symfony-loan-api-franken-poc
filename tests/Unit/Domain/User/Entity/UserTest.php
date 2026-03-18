<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\Entity;

use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testCreateUser(): void
    {
        $id = UserId::generate();
        $user = new User($id, 'John Doe', new Email('john@example.com'));

        self::assertTrue($user->getId()->equals($id));
        self::assertSame('John Doe', $user->getName());
        self::assertSame('john@example.com', $user->getEmail()->value);
        self::assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
    }

    public function testChangeEmail(): void
    {
        $user = new User(UserId::generate(), 'John', new Email('john@old.com'));
        $user->changeEmail(new Email('john@new.com'));

        self::assertSame('john@new.com', $user->getEmail()->value);
    }
}
