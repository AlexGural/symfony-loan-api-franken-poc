<?php

declare(strict_types=1);

namespace App\Presentation\Response;

use OpenApi\Attributes as OA;

/**
 * OpenAPI schema only
 * Matches the structure returned by DomainExceptionListener.
 */
#[OA\Schema(schema: 'ErrorResponse')]
final readonly class ErrorResponse
{
    public function __construct(
        #[OA\Property(properties: [
            new OA\Property(property: 'code', type: 'integer', example: 1001),
        ], type: 'object')]
        public object $error,
    ) {
    }
}
