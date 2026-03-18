<?php

declare(strict_types=1);

namespace App\Presentation\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class HttpStatus
{
    public function __construct(
        public int $code = 200,
    ) {
    }
}
