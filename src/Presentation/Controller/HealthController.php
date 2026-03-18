<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Health')]
final class HealthController
{
    #[Route('/api/health', methods: ['GET'])]
    #[OA\Get(summary: 'Health check')]
    #[OA\Response(
        response: 200,
        description: 'Service is healthy',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', type: 'string', example: 'ok'),
                new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2026-03-01T12:00:00+00:00'),
            ],
            type: 'object',
        ),
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'timestamp' => new \DateTimeImmutable()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
