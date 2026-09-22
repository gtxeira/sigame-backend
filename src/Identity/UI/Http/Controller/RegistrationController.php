<?php

declare(strict_types=1);

namespace App\Identity\UI\Http\Controller;

use App\Helpers\ApiResponse;
use App\Identity\Application\UseCase\RegisterUser\RegisterUserCommand;
use App\Identity\Application\UseCase\RegisterUser\RegisterUserHandler;
use App\Identity\UI\Http\Request\RegisterUserRequest;
use DateTimeInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller para registro de usuários.
 */
final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly RegisterUserHandler $registerUserHandler,
    ) {
    }

    /**
     * RF01 — Cadastrar usuário.
     */
    #[Route('/api/v1/auth/register', methods: ['POST'])]
    #[OA\Response(
        response: 200,
        description: 'Usuário cadastrado com sucesso.',
        content: new OA\JsonContent(
            example: [
                'id' => '01a0c64d-ff09-7605-ae79-0defb533f7b6',
                'cpf' => '477.051.220-14',
                'email' => 'user@example.com',
                'name' => 'John Doe',
                'level' => 1,
                'status' => 'ACTIVE',
                'createdAt' => '2026-09-21T10:00:00.000+00:00',
            ],
            oneOf: [
                new OA\Schema(ref: '#/components/schemas/ApiResponseSuccess'),
            ],
        ),
    )]
    #[OA\Tag(name: 'Auth')]
    public function register(
        #[MapRequestPayload]
        RegisterUserRequest $request,
    ): JsonResponse {
        $command = new RegisterUserCommand(
            cpf: $request->cpf,
            email: $request->email,
            name: $request->name,
            password: $request->password,
        );

        $user = ($this->registerUserHandler)($command);

        return new ApiResponse(
            isSuccess: true,
            data: [
                'id' => $user->id()->value(),
                'cpf' => $user->cpf()->formatted(),
                'email' => $user->email()->value(),
                'name' => $user->name(),
                'level' => $user->level()->value(),
                'status' => $user->status()->value,
                'createdAt' => $user->createdAt()->format(DateTimeInterface::RFC3339_EXTENDED),
            ],
        )->toJsonResponse(Response::HTTP_CREATED);
    }
}
