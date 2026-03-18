<?php

declare(strict_types=1);

namespace App\Presentation\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateUserRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        #[OA\Property(example: 'John Doe')]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Email]
        #[OA\Property(format: 'email', example: 'john@example.com')]
        public string $email,
    ) {
    }
}
