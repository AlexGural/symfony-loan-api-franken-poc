<?php

declare(strict_types=1);

namespace App\Presentation\Response\User;

use App\Domain\User\Entity\User;
use App\Presentation\Response\ApiResponseInterface;
use OpenApi\Attributes as OA;

final readonly class UserResponseDto implements ApiResponseInterface
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890')]
        public string $id,

        #[OA\Property(example: 'John Doe')]
        public string $name,

        #[OA\Property(format: 'email', example: 'john@example.com')]
        public string $email,

        #[OA\Property(format: 'date-time', example: '2026-03-01T12:00:00+00:00')]
        public string $createdAt,
    ) {
    }

    #[\NoDiscard]
    public static function toResponse(User $user): self
    {
        return new self(
            id: $user->getId()->value,
            name: $user->getName(),
            email: $user->getEmail()->value,
            createdAt: $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
