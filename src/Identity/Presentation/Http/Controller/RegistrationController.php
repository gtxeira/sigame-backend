<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\RegisterUser\RegisterUserCommand;
use App\Identity\Application\UseCase\RegisterUser\RegisterUserHandler;
use App\Identity\Presentation\Http\Request\RegisterUserRequest;
use App\Identity\Presentation\Http\Response\RegisterUserResponse;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
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
#[Route('/api/v1')]
#[OA\Tag(name: 'Autenticação')]
final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly RegisterUserHandler $registerUserHandler,
        private readonly ResultResponder $resultResponder,
    ) {
    }

    /**
     * Cadastrar usuário
     *
     * Cria uma conta local com e-mail e senha. O CPF é opcional e, quando
     * informado, pode vir formatado. A operação não autentica o usuário; o
     * login deve ser feito em seguida pelo endpoint de autenticação.
     */
    #[Route('/auth/register', name: 'app_auth_register', methods: ['POST'], priority: 10)]
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
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['email' => 'O email é obrigatório.'],
                summary: 'Erros de Validação',
                description: 'Um ou mais campos do payload contêm dados inválidos.',
            ),
            'CPF_INVALID' => new ApiErrorExample(
                message: 'O CPF possui dígitos verificadores inválidos.',
                details: ['cpf'],
                summary: 'CPF inválido',
                description: 'O CPF não passou na validação de dígitos verificadores.',
            ),
            'EMAIL_INVALID' => new ApiErrorExample(
                message: 'O e-mail informado é inválido.',
                details: ['email'],
                summary: 'E-mail inválido',
                description: 'O e-mail não passou na validação de formato.',
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
            'USER_CPF_ALREADY_EXISTS' => new ApiErrorExample(
                message: 'Já existe um usuário com este CPF.',
                details: ['cpf'],
                summary: 'CPF já cadastrado',
                description: 'Já existe um usuário com o CPF informado.',
            ),
            'EMAIL_ALREADY_EXISTS' => new ApiErrorExample(
                message: 'Já existe um usuário com este e-mail.',
                details: ['email'],
                summary: 'E-mail já cadastrado',
                description: 'Já existe um usuário com o e-mail informado.',
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
