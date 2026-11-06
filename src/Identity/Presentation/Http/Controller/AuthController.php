<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\AuthenticateUser\AuthenticateUserCommand;
use App\Identity\Application\UseCase\AuthenticateUser\AuthenticateUserHandler;
use App\Identity\Application\UseCase\Logout\LogoutCommand;
use App\Identity\Application\UseCase\Logout\LogoutHandler;
use App\Identity\Application\UseCase\RefreshToken\RefreshTokenCommand;
use App\Identity\Application\UseCase\RefreshToken\RefreshTokenHandler;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\LoginRequest;
use App\Identity\Presentation\Http\Response\RefreshTokenCookieFactory;
use App\Identity\Presentation\Http\Response\TokenPairResponseFactory;
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
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ResultResponder;
use App\Shared\Presentation\Http\Security\CookieOriginGuard;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints de autenticação: login local, login social, refresh e logout.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Autenticação')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthenticateUserHandler $authenticateUserHandler,
        private readonly RefreshTokenHandler $refreshTokenHandler,
        private readonly LogoutHandler $logoutHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RefreshTokenCookieFactory $cookieFactory,
        private readonly TokenPairResponseFactory $tokenPairResponseFactory,
        private readonly RateLimitGuard $rateLimitGuard,
        private readonly CookieOriginGuard $cookieOriginGuard,
        #[Autowire(service: 'limiter.auth_login')]
        private readonly RateLimiterFactoryInterface $loginLimiter,
        #[Autowire(service: 'limiter.auth_refresh')]
        private readonly RateLimiterFactoryInterface $refreshLimiter,
    ) {
    }

    /**
     * Autenticar com e-mail e senha
     *
     * Valida as credenciais informadas e emite um access token JWT. O refresh
     * token é entregue em cookie HttpOnly e não aparece no corpo da resposta.
     * O endpoint é limitado por e-mail e IP.
     */
    #[Route('/auth/login', name: 'app_auth_login', methods: ['POST'])]
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
        $this->rateLimitGuard->assertWithin(
            $this->loginLimiter,
            mb_strtolower(trim($request->email)).'|'.($httpRequest->getClientIp() ?? 'unknown'),
        );

        $result = ($this->authenticateUserHandler)(new AuthenticateUserCommand(
            email: $request->email,
            password: $request->password,
        ));

        return $this->tokenPairResponseFactory->fromResult($result);
    }

    /**
     * Renovar o par de tokens
     *
     * Lê o refresh token do cookie HttpOnly e emite um novo par. O token
     * anterior é invalidado; a reapresentação de um token revogado revoga toda
     * a família de tokens daquela sessão.
     */
    #[Route('/auth/refresh', name: 'app_auth_refresh', methods: ['POST'])]
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
            'ORIGIN_NOT_ALLOWED' => new ApiErrorExample(
                message: 'A origem da requisição não é permitida para esta operação.',
                summary: 'Origem não permitida',
                description: 'O header Origin não corresponde às origens permitidas para operações com cookie.',
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
        $this->cookieOriginGuard->assertAllowed($httpRequest);
        $this->rateLimitGuard->assertWithin($this->refreshLimiter, $httpRequest->getClientIp() ?? 'unknown');

        $rawToken = $httpRequest->cookies->get($this->cookieFactory->name());

        $result = null === $rawToken
            ? Result::failure([IdentityErrors::refreshTokenInvalid()])
            : ($this->refreshTokenHandler)(new RefreshTokenCommand($rawToken));

        if ($result->isFailure()) {
            $response = $this->resultResponder->failure($result);
            $response->headers->setCookie($this->cookieFactory->clear());

            return $response;
        }

        return $this->tokenPairResponseFactory->fromResult($result);
    }

    /**
     * Encerrar a sessão atual
     *
     * Revoga a família do refresh token apresentado no cookie e limpa o cookie
     * no cliente. A operação é idempotente para um cookie já revogado.
     */
    #[Route('/auth/logout', name: 'app_auth_logout', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Sessão encerrada.')]
    #[ForbiddenResponse(
        examples: [
            'ORIGIN_NOT_ALLOWED' => new ApiErrorExample(
                message: 'A origem da requisição não é permitida para esta operação.',
                summary: 'Origem não permitida',
                description: 'O header Origin não corresponde às origens permitidas para operações com cookie.',
            ),
        ],
    )]
    #[InternalErrorResponse]
    public function logout(Request $httpRequest): Response
    {
        $this->cookieOriginGuard->assertAllowed($httpRequest);
        $rawToken = $httpRequest->cookies->get($this->cookieFactory->name());

        ($this->logoutHandler)(new LogoutCommand(refreshToken: $rawToken));

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookieFactory->clear());

        return $response;
    }

    /**
     * Encerrar todas as sessões
     *
     * Revoga todas as famílias de refresh tokens do usuário autenticado,
     * encerrando a sessão em todos os dispositivos.
     */
    #[Route('/auth/logout/all', name: 'app_auth_logout_all', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Todas as sessões foram encerradas.')]
    #[UnauthorizedResponse]
    #[InternalErrorResponse]
    public function logoutAll(): Response
    {
        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        ($this->logoutHandler)(new LogoutCommand(
            allDevices: true,
            userId: $securityUser->user()->id(),
        ));

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookieFactory->clear());

        return $response;
    }
}
