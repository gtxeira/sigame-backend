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
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnsupportedMediaTypeResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ResultResponder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
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
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.auth_register')]
        private readonly RateLimiterFactoryInterface $registerLimiter,
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
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'O email é obrigatório.',
                field: 'email',
                summary: 'Erros de Validação',
                description: 'Um ou mais campos do payload contêm dados inválidos.',
            ),
            'INVALID_FORMAT' => new ApiErrorExample(
                message: 'O CPF possui dígitos verificadores inválidos.',
                field: 'cpf',
                additional: [
                    [
                        'code' => 'INVALID_FORMAT',
                        'field' => 'email',
                        'message' => 'O e-mail informado é inválido.',
                    ],
                ],
                summary: 'Formato inválido',
                description: 'O CPF ou o e-mail não passaram na validação de formato.',
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
                field: 'cpf',
                summary: 'CPF já cadastrado',
                description: 'Já existe um usuário com o CPF informado.',
            ),
            'EMAIL_ALREADY_EXISTS' => new ApiErrorExample(
                message: 'Já existe um usuário com este e-mail.',
                field: 'email',
                summary: 'E-mail já cadastrado',
                description: 'Já existe um usuário com o e-mail informado.',
            ),
        ],
        fields: [
            'cpf' => 'CPF do usuário.',
            'email' => 'E-mail do usuário.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function register(
        #[MapRequestPayload]
        RegisterUserRequest $request,
        Request $httpRequest,
    ): JsonResponse {
        $this->rateLimitGuard->assertWithin(
            $this->registerLimiter,
            mb_strtolower(trim($request->email)).'|'.($httpRequest->getClientIp() ?? 'unknown'),
        );

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
