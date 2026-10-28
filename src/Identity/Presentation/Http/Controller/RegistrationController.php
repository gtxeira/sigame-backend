<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\RegisterUser\RegisterUserCommand;
use App\Identity\Application\UseCase\RegisterUser\RegisterUserHandler;
use App\Identity\Presentation\Http\Request\RegisterUserRequest;
use App\Identity\Presentation\Http\Response\RegisterUserResponse;
use App\Shared\Presentation\Http\OpenApi\DomainErrorResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\Response\ResultResponder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller para registro de usuários.
 */
#[OA\Tag(name: 'Auth', description: 'Operações relacionadas à autenticação de usuários')]
final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly RegisterUserHandler $registerUserHandler,
        private readonly ResultResponder $resultResponder,
    ) {
    }

    /**
     * RF01 — Cadastrar usuário.
     */
    #[Route('/api/v1/auth/register', name: 'app_auth_register', methods: ['POST'])]
    #[SuccessResponse(
        dataModel: RegisterUserResponse::class,
        response: Response::HTTP_CREATED,
        description: 'Usuário cadastrado com sucesso.',
    )]
    #[ValidationErrorResponse]
    #[DomainErrorResponse]
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

        $result = ($this->registerUserHandler)($command);

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(fn ($user) => RegisterUserResponse::fromEntity($user)),
            Response::HTTP_CREATED,
        );
    }
}
