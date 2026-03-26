<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

final class UserControllerTest extends ApiTestCase
{
    public function testCreateUser(): void
    {
        $response = $this->jsonRequest('POST', '/api/users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        self::assertSame(201, $this->getStatusCode());
        self::assertSame('John Doe', $response['name']);
        self::assertSame('john@example.com', $response['email']);
        self::assertArrayHasKey('id', $response);
        self::assertArrayHasKey('createdAt', $response);
    }

    public function testCreateUserValidation(): void
    {
        $this->jsonRequest('POST', '/api/users', [
            'name' => '',
            'email' => 'not-email',
        ]);

        self::assertSame(422, $this->getStatusCode());
    }

    public function testCreateDuplicateEmailReturns409(): void
    {
        $this->jsonRequest('POST', '/api/users', [
            'name' => 'First',
            'email' => 'duplicate@example.com',
        ]);
        self::assertSame(201, $this->getStatusCode());

        $this->jsonRequest('POST', '/api/users', [
            'name' => 'Second',
            'email' => 'duplicate@example.com',
        ]);
        self::assertSame(409, $this->getStatusCode());
    }

    public function testListUsersEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/users');

        self::assertSame(200, $this->getStatusCode());
        self::assertSame([], $response);
    }

    public function testListUsersReturnsCreatedUsers(): void
    {
        $this->jsonRequest('POST', '/api/users', ['name' => 'Alice', 'email' => 'a@test.com']);
        $this->jsonRequest('POST', '/api/users', ['name' => 'Bob', 'email' => 'b@test.com']);

        $response = $this->jsonRequest('GET', '/api/users');

        self::assertSame(200, $this->getStatusCode());
        self::assertCount(2, $response);
    }

    public function testGetUserById(): void
    {
        $created = $this->jsonRequest('POST', '/api/users', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);

        $response = $this->jsonRequest('GET', '/api/users/' . $created['id']);

        self::assertSame(200, $this->getStatusCode());
        self::assertSame('Jane', $response['name']);
        self::assertSame('jane@example.com', $response['email']);
        self::assertSame($created['id'], $response['id']);
    }

    public function testGetNonExistentUserReturns404(): void
    {
        $nonExistentUserId = Uuid::v7()->toRfc4122();
        $this->jsonRequest('GET', '/api/users/' . $nonExistentUserId);

        self::assertSame(404, $this->getStatusCode());
    }

    public function testGetUserWithInvalidUuidReturns404(): void
    {
        $this->jsonRequest('GET', '/api/users/not-a-uuid');

        self::assertSame(404, $this->getStatusCode());
    }
}
