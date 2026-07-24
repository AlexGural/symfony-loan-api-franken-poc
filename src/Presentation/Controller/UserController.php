<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\User\UseCase\CreateUserUseCase;
use App\Application\User\UseCase\GetUserUseCase;
use App\Application\User\UseCase\ListUsersUseCase;
use App\Presentation\Attribute\HttpStatus;
use App\Presentation\Request\CreateUserRequestDto;
use App\Presentation\Response\ErrorResponse;
use App\Presentation\Response\User\UserResponseDto;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Users')]
#[Route('/api/users')]
final readonly class UserController
{
    public function __construct(
        private CreateUserUseCase $createUser,
        private GetUserUseCase $getUser,
        private ListUsersUseCase $listUsers,
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(summary: 'List all users')]
    #[OA\Response(
        response: 200,
        description: 'List of users',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: UserResponseDto::class))),
    )]
    /** @return UserResponseDto[] */
    public function listUsersAction(): array
    {
        return array_map(
            UserResponseDto::toResponse(...),
            $this->listUsers->execute(),
        );
    }

    #[Route('/{id}', methods: ['GET'])]
    #[OA\Get(summary: 'Get user by ID')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'User details', content: new OA\JsonContent(ref: new Model(type: UserResponseDto::class)))]
    #[OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)))]
    public function getUserAction(Uuid $id): UserResponseDto
    {
        return $this->getUser->execute($id->toRfc4122())
            |> UserResponseDto::toResponse(...);
    }

    #[HttpStatus(201)]
    #[Route('', methods: ['POST'])]
    #[OA\Post(summary: 'Create a new user')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CreateUserRequestDto::class)))]
    #[OA\Response(response: 201, description: 'User created', content: new OA\JsonContent(ref: new Model(type: UserResponseDto::class)))]
    #[OA\Response(response: 409, description: 'User already exists', content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)))]
    public function createUserAction(#[MapRequestPayload] CreateUserRequestDto $dto): UserResponseDto
    {
        return $this->createUser->execute($dto->name, $dto->email)
            |> UserResponseDto::toResponse(...);
    }
}
