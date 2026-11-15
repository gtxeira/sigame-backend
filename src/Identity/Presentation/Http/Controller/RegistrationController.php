<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\RegisterUser\RegisterUserCommand;
use App\Identity\Application\UseCase\RegisterUser\RegisterUserHandler;
use App\Identity\Presentation\Http\Request\RegisterUserRequest;
use App\Identity\Presentation\Http\Response\RegistrationAcceptedResponse;
use App\Shared\Domain\Functional\Result;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
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
 * Controller para solicitação de cadastro.
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
     * Solicitar cadastro
     *
     * Sempre responde `202` com o mesmo corpo, exista ou não conta com o
     * e-mail, para não permitir enumeração. Se o e-mail for novo, uma conta é
     * criada e recebe o código de confirmação. Se já existir, nenhuma conta é
     * criada e o dono recebe um aviso com instruções para entrar ou recuperar
     * a senha.
     */
    #[Route('/auth/register', name: 'app_auth_register', methods: ['POST'], priority: 10)]
    #[SuccessResponse(
        dataModel: RegistrationAcceptedResponse::class,
        response: Response::HTTP_ACCEPTED,
        description: 'Solicitação aceita; o resultado não revela se o e-mail já tinha conta.',
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
                message: 'O e-mail informado é inválido.',
                field: 'email',
                summary: 'Formato inválido',
                description: 'O e-mail não passou na validação de formato.',
            ),
        ],
        fields: [
            'email' => 'E-mail do usuário.',
            'name' => 'Nome completo do usuário.',
            'password' => 'Senha do usuário.',
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
            email: $request->email,
            name: $request->name,
            password: $request->password,
        );

        $result = ($this->registerUserHandler)($command);

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            Result::success(RegistrationAcceptedResponse::pending()),
            Response::HTTP_ACCEPTED,
        );
    }
}
