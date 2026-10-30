<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\RegisterUser\RegisterUserCommand;
use App\Identity\Application\UseCase\RegisterUser\RegisterUserHandler;
use App\Identity\Presentation\Http\Request\RegisterUserRequest;
use App\Identity\Presentation\Http\Response\RegisterUserResponse;
use App\Shared\Presentation\Http\OpenApi\ApiExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\ResourceConflictResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\UnsupportedMediaTypeResponse;
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
        examples: [
            'USUARIO_CADASTRADO' => new ApiExample(
                summary: 'Usuário cadastrado',
                description: 'Cadastro concluído; os dados do usuário são retornados em `data`.',
            ),
        ],
    )]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiExample(
                summary: 'Erros de Validação',
                description: 'Um ou mais campos do payload contêm dados inválidos.',
                value: [
                    'errors' => [
                        [
                            'code' => 'VALIDATION_ERROR',
                            'message' => 'Um ou mais erros de validação foram encontrados.',
                            'details' => [
                                [
                                    'field' => 'email',
                                    'message' => 'O email é obrigatório.',
                                ],
                            ],
                        ],
                    ],
                ],
            ),
            'CPF_INVALID' => new ApiExample(
                summary: 'CPF inválido',
                description: 'O CPF não passou na validação de dígitos verificadores.',
                value: [
                    'errors' => [
                        [
                            'code' => 'CPF_INVALID',
                            'message' => 'O CPF possui dígitos verificadores inválidos.',
                            'details' => [
                                ['field' => 'cpf'],
                            ],
                        ],
                    ],
                ],
            ),
            'EMAIL_INVALID' => new ApiExample(
                summary: 'E-mail inválido',
                description: 'O e-mail não passou na validação de formato.',
                value: [
                    'errors' => [
                        [
                            'code' => 'EMAIL_INVALID',
                            'message' => 'O e-mail informado é inválido.',
                            'details' => [
                                ['field' => 'email'],
                            ],
                        ],
                    ],
                ],
            ),
        ],
        fields: [
            'cpf' => 'CPF do usuário.',
            'email' => 'E-mail do usuário.',
            'name' => 'Nome completo do usuário.',
            'password' => 'Senha do usuário.',
        ],
    )]
    #[ResourceConflictResponse(
        examples: [
            'USER_CPF_ALREADY_EXISTS' => new ApiExample(
                summary: 'CPF já cadastrado',
                description: 'Já existe um usuário com o CPF informado.',
                value: [
                    'errors' => [
                        [
                            'code' => 'USER_CPF_ALREADY_EXISTS',
                            'message' => 'Já existe um usuário com este CPF.',
                            'details' => [
                                ['field' => 'cpf'],
                            ],
                        ],
                    ],
                ],
            ),
            'EMAIL_ALREADY_EXISTS' => new ApiExample(
                summary: 'E-mail já cadastrado',
                description: 'Já existe um usuário com o e-mail informado.',
                value: [
                    'errors' => [
                        [
                            'code' => 'EMAIL_ALREADY_EXISTS',
                            'message' => 'Já existe um usuário com este e-mail.',
                            'details' => [
                                ['field' => 'email'],
                            ],
                        ],
                    ],
                ],
            ),
        ],
        fields: [
            'cpf' => 'CPF do usuário.',
            'email' => 'E-mail do usuário.',
        ],
    )]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
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
