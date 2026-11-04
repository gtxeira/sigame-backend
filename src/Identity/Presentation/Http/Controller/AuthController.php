<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\DTO\TokenPair;
use App\Identity\Application\UseCase\AssignCpf\AssignCpfCommand;
use App\Identity\Application\UseCase\AssignCpf\AssignCpfHandler;
use App\Identity\Application\UseCase\AuthenticateUser\AuthenticateUserCommand;
use App\Identity\Application\UseCase\AuthenticateUser\AuthenticateUserHandler;
use App\Identity\Application\UseCase\AuthenticateWithSocialProvider\AuthenticateWithSocialProviderCommand;
use App\Identity\Application\UseCase\AuthenticateWithSocialProvider\AuthenticateWithSocialProviderHandler;
use App\Identity\Application\UseCase\Logout\LogoutCommand;
use App\Identity\Application\UseCase\Logout\LogoutHandler;
use App\Identity\Application\UseCase\RefreshToken\RefreshTokenCommand;
use App\Identity\Application\UseCase\RefreshToken\RefreshTokenHandler;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\AssignCpfRequest;
use App\Identity\Presentation\Http\Request\LoginRequest;
use App\Identity\Presentation\Http\Request\SocialLoginRequest;
use App\Identity\Presentation\Http\Response\CurrentUserResponse;
use App\Identity\Presentation\Http\Response\RefreshTokenCookieFactory;
use App\Identity\Presentation\Http\Response\TokenResponse;
use App\Shared\Domain\Functional\Result;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\ResourceConflictResponse;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\UnsupportedMediaTypeResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\Response\ApiResponse;
use App\Shared\Presentation\Http\Response\ResultResponder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints de autenticação: login local, login social, refresh, logout, me e CPF.
 */
#[OA\Tag(name: 'Auth', description: 'Operações relacionadas à autenticação de usuários')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthenticateUserHandler $authenticateUserHandler,
        private readonly AuthenticateWithSocialProviderHandler $socialHandler,
        private readonly RefreshTokenHandler $refreshTokenHandler,
        private readonly LogoutHandler $logoutHandler,
        private readonly AssignCpfHandler $assignCpfHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RefreshTokenCookieFactory $cookieFactory,
        #[Autowire(service: 'limiter.auth_login')]
        private readonly RateLimiterFactoryInterface $loginLimiter,
        #[Autowire(service: 'limiter.auth_social')]
        private readonly RateLimiterFactoryInterface $socialLimiter,
        #[Autowire(service: 'limiter.auth_refresh')]
        private readonly RateLimiterFactoryInterface $refreshLimiter,
        #[Autowire(service: 'limiter.auth_cpf')]
        private readonly RateLimiterFactoryInterface $cpfLimiter,
    ) {
    }

    /**
     * RF02 — autenticar com e-mail e senha.
     */
    #[Route('/api/v1/auth/login', name: 'app_auth_login', methods: ['POST'])]
    #[SuccessResponse(
        dataModel: TokenResponse::class,
        description: 'Autenticado com sucesso; o refresh token é entregue no cookie HttpOnly.',
    )]
    #[UnauthorizedResponse(
        examples: [
            'INVALID_CREDENTIALS' => new ApiErrorExample(
                message: 'E-mail ou senha inválidos.',
                summary: 'Credenciais inválidas',
                description: 'O e-mail não existe ou a senha informada está errada.',
            ),
        ],
        fields: ['credentials' => 'Credenciais de login.'],
    )]
    #[ForbiddenResponse(
        examples: [
            'ACCOUNT_SUSPENDED' => new ApiErrorExample(
                message: 'Esta conta está suspensa e não pode autenticar.',
                summary: 'Conta suspensa',
                description: 'A conta existe, mas está suspensa.',
            ),
        ],
        fields: ['status' => 'Situação da conta.'],
    )]
    #[ValidationErrorResponse(fields: [
        'email' => 'E-mail do usuário.',
        'password' => 'Senha do usuário.',
    ])]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function login(
        #[MapRequestPayload] LoginRequest $request,
        Request $httpRequest,
    ): JsonResponse {
        $this->assertWithinLimit(
            $this->loginLimiter,
            mb_strtolower(trim($request->email)).'|'.($httpRequest->getClientIp() ?? 'unknown'),
        );

        $result = ($this->authenticateUserHandler)(new AuthenticateUserCommand(
            email: $request->email,
            password: $request->password,
        ));

        return $this->tokenResponse($result);
    }

    /**
     * Login social por authorization code.
     */
    #[Route('/api/v1/auth/social/{provider}', name: 'app_auth_social', methods: ['POST'])]
    #[SuccessResponse(
        dataModel: TokenResponse::class,
        description: 'Autenticado no provedor social; o refresh token é entregue no cookie HttpOnly.',
    )]
    #[UnauthorizedResponse(
        examples: [
            'SOCIAL_AUTHENTICATION_FAILED' => new ApiErrorExample(
                message: 'Não foi possível autenticar com o provedor social.',
                summary: 'Falha no provedor',
                description: 'O authorization code foi recusado ou o id_token é inválido.',
            ),
        ],
    )]
    #[ForbiddenResponse(
        examples: [
            'ACCOUNT_SUSPENDED' => new ApiErrorExample(
                message: 'Esta conta está suspensa e não pode autenticar.',
                summary: 'Conta suspensa',
                description: 'A conta vinculada ao provedor está suspensa.',
            ),
        ],
        fields: ['status' => 'Situação da conta.'],
    )]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['code' => 'O authorization code é obrigatório.'],
                summary: 'Erros de Validação',
                description: 'O payload do login social contém dados inválidos.',
            ),
            'SOCIAL_PROVIDER_UNSUPPORTED' => new ApiErrorExample(
                message: 'O provedor de login "apple" não é suportado.',
                details: ['provider'],
                summary: 'Provedor não suportado',
                description: 'Somente o provedor google está habilitado nesta entrega.',
            ),
            'SOCIAL_EMAIL_NOT_VERIFIED' => new ApiErrorExample(
                message: 'O provedor social não confirmou a verificação do e-mail.',
                details: ['email'],
                summary: 'E-mail não verificado',
                description: 'O provedor não confirmou que o e-mail pertence ao usuário.',
            ),
        ],
        fields: [
            'code' => 'Authorization code.',
            'redirectUri' => 'Redirect URI usada na autorização.',
            'provider' => 'Provedor social.',
            'email' => 'E-mail devolvido pelo provedor.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function social(
        string $provider,
        #[MapRequestPayload] SocialLoginRequest $request,
        Request $httpRequest,
    ): JsonResponse {
        $this->assertWithinLimit(
            $this->socialLimiter,
            mb_strtolower($provider).'|'.($httpRequest->getClientIp() ?? 'unknown'),
        );

        $result = ($this->socialHandler)(new AuthenticateWithSocialProviderCommand(
            provider: $provider,
            authorizationCode: $request->code,
            redirectUri: $request->redirectUri,
            codeVerifier: $request->codeVerifier,
            name: $request->name,
        ));

        return $this->tokenResponse($result);
    }

    /**
     * Rotaciona o par access token + refresh token.
     */
    #[Route('/api/v1/auth/refresh', name: 'app_auth_refresh', methods: ['POST'])]
    #[SuccessResponse(
        dataModel: TokenResponse::class,
        description: 'Par de tokens rotacionado; o novo refresh token substitui o cookie anterior.',
    )]
    #[UnauthorizedResponse(
        examples: [
            'REFRESH_TOKEN_INVALID' => new ApiErrorExample(
                message: 'O token de renovação é inválido.',
                summary: 'Refresh inválido',
                description: 'O cookie não contém um refresh token conhecido.',
            ),
            'REFRESH_TOKEN_EXPIRED' => new ApiErrorExample(
                message: 'O token de renovação expirou. Autentique-se novamente.',
                summary: 'Refresh expirado',
                description: 'O refresh token passou do prazo de validade.',
            ),
            'REFRESH_TOKEN_REUSED' => new ApiErrorExample(
                message: 'O token de renovação já foi utilizado. Autentique-se novamente.',
                summary: 'Refresh reutilizado',
                description: 'Um token revogado foi reapresentado; a família foi revogada.',
            ),
            'REFRESH_TOKEN_ALREADY_ROTATED' => new ApiErrorExample(
                message: 'O token de renovação acabou de ser rotacionado.',
                summary: 'Refresh recém-rotacionado',
                description: 'Requisição concorrente dentro da janela de graça.',
            ),
        ],
    )]
    #[ForbiddenResponse(
        examples: [
            'ACCOUNT_SUSPENDED' => new ApiErrorExample(
                message: 'Esta conta está suspensa e não pode autenticar.',
                summary: 'Conta suspensa',
                description: 'A conta da sessão foi suspensa.',
            ),
        ],
        fields: ['status' => 'Situação da conta.'],
    )]
    #[ResourceConflictResponse(
        examples: [
            'REFRESH_IN_PROGRESS' => new ApiErrorExample(
                message: 'Já existe uma renovação em andamento para esta sessão.',
                summary: 'Renovação concorrente',
                description: 'Outra requisição está rotacionando este refresh token.',
            ),
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function refresh(Request $httpRequest): JsonResponse
    {
        $this->assertWithinLimit($this->refreshLimiter, $httpRequest->getClientIp() ?? 'unknown');

        $rawToken = $httpRequest->cookies->get($this->cookieFactory->name());

        $result = null === $rawToken
            ? Result::failure([IdentityErrors::refreshTokenInvalid()])
            : ($this->refreshTokenHandler)(new RefreshTokenCommand($rawToken));

        if ($result->isFailure()) {
            $response = $this->resultResponder->failure($result);
            $response->headers->setCookie($this->cookieFactory->clear());

            return $response;
        }

        return $this->tokenResponse($result);
    }

    /**
     * Encerra a sessão do cookie atual.
     */
    #[Route('/api/v1/auth/logout', name: 'app_auth_logout', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Sessão encerrada.')]
    #[InternalErrorResponse]
    public function logout(Request $httpRequest): Response
    {
        $rawToken = $httpRequest->cookies->get($this->cookieFactory->name());

        ($this->logoutHandler)(new LogoutCommand(refreshToken: $rawToken));

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookieFactory->clear());

        return $response;
    }

    /**
     * Encerra todas as sessões do usuário autenticado.
     */
    #[Route('/api/v1/auth/logout/all', name: 'app_auth_logout_all', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Todas as sessões foram encerradas.')]
    #[UnauthorizedResponse]
    #[InternalErrorResponse]
    public function logoutAll(): Response
    {
        $securityUser = $this->getUser();
        \assert($securityUser instanceof SecurityUser);

        ($this->logoutHandler)(new LogoutCommand(
            allDevices: true,
            userId: $securityUser->user()->id(),
        ));

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookieFactory->clear());

        return $response;
    }

    /**
     * Dados públicos do usuário autenticado.
     */
    #[Route('/api/v1/auth/me', name: 'app_auth_me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[SuccessResponse(dataModel: CurrentUserResponse::class, description: 'Dados do usuário autenticado.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[InternalErrorResponse]
    public function me(): JsonResponse
    {
        $securityUser = $this->getUser();
        \assert($securityUser instanceof SecurityUser);

        return ApiResponse::ok(
            CurrentUserResponse::fromEntity($securityUser->user()),
        )->toJsonResponse();
    }

    /**
     * Define o CPF do usuário autenticado (write-once).
     */
    #[Route('/api/v1/auth/me/cpf', name: 'app_auth_me_cpf', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')]
    #[SuccessResponse(dataModel: CurrentUserResponse::class, description: 'CPF definido com sucesso.')]
    #[UnauthorizedResponse]
    #[ForbiddenResponse]
    #[ResourceConflictResponse(
        examples: [
            'USER_CPF_ALREADY_SET' => new ApiErrorExample(
                message: 'O CPF já foi definido e não pode ser alterado.',
                details: ['cpf'],
                summary: 'CPF já definido',
                description: 'O CPF é write-once: uma vez definido, o próprio usuário não pode alterá-lo.',
            ),
            'USER_CPF_ALREADY_EXISTS' => new ApiErrorExample(
                message: 'Já existe um usuário com este CPF.',
                details: ['cpf'],
                summary: 'CPF em uso',
                description: 'O CPF informado pertence a outro usuário.',
            ),
        ],
        fields: ['cpf' => 'CPF do usuário.'],
    )]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['cpf' => 'O CPF é obrigatório.'],
                summary: 'Erros de Validação',
                description: 'O CPF não foi informado ou está em formato inválido.',
            ),
            'CPF_INVALID' => new ApiErrorExample(
                message: 'O CPF possui dígitos verificadores inválidos.',
                details: ['cpf'],
                summary: 'CPF inválido',
                description: 'O CPF não passou na validação de dígitos verificadores.',
            ),
        ],
        fields: ['cpf' => 'CPF do usuário.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function assignCpf(
        #[MapRequestPayload] AssignCpfRequest $request,
    ): JsonResponse {
        $securityUser = $this->getUser();
        \assert($securityUser instanceof SecurityUser);

        $user = $securityUser->user();
        $this->assertWithinLimit($this->cpfLimiter, $user->id()->value());

        $result = ($this->assignCpfHandler)(new AssignCpfCommand(
            userId: $user->id(),
            cpf: $request->cpf,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(static fn (User $updated): CurrentUserResponse => CurrentUserResponse::fromEntity($updated)),
        );
    }

    /**
     * @param Result<TokenPair> $result
     */
    private function tokenResponse(Result $result): JsonResponse
    {
        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        $pair = $result->value();

        $response = $this->resultResponder->success(
            $result->map(static fn (TokenPair $tokenPair): TokenResponse => TokenResponse::fromTokenPair($tokenPair)),
        );
        $response->headers->setCookie($this->cookieFactory->create(
            rawToken: $pair->refreshToken,
            expiresAt: $pair->refreshExpiresAt,
        ));

        return $response;
    }

    private function assertWithinLimit(RateLimiterFactoryInterface $limiter, string $key): void
    {
        $limit = $limiter->create($key)->consume(1);

        if ($limit->isAccepted()) {
            return;
        }

        throw new TooManyRequestsHttpException(
            retryAfter: max(1, $limit->getRetryAfter()->getTimestamp() - time()),
            headers: [
                'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
                'X-RateLimit-Limit' => (string) $limit->getLimit(),
            ],
        );
    }
}
