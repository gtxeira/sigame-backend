<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\Password\RequestPasswordResetHandler;
use App\Identity\Application\UseCase\Password\ResetPasswordHandler;
use App\Identity\Presentation\Http\Request\PasswordForgotRequest;
use App\Identity\Presentation\Http\Request\PasswordResetRequest;
use App\Identity\Presentation\Http\Response\RefreshTokenCookieFactory;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnsupportedMediaTypeResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ResultResponder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recuperação de senha para quem não está autenticado.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Autenticação')]
final class PasswordController extends AbstractController
{
    public function __construct(
        private readonly RequestPasswordResetHandler $requestPasswordResetHandler,
        private readonly ResetPasswordHandler $resetPasswordHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RefreshTokenCookieFactory $cookieFactory,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.auth_password_forgot')]
        private readonly RateLimiterFactoryInterface $forgotLimiter,
        #[Autowire(service: 'limiter.auth_password_reset')]
        private readonly RateLimiterFactoryInterface $resetLimiter,
    ) {
    }

    /**
     * Solicitar redefinição de senha
     *
     * Envia um token de uso único para o e-mail canônico, quando a conta
     * existe e está ativa. A resposta é sempre 204 para não revelar quais
     * endereços estão cadastrados. Pedidos repetidos dentro do cooldown não
     * geram novo e-mail.
     */
    #[Route('/auth/password/forgot', name: 'app_auth_password_forgot', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Solicitação processada.')]
    #[ValidationErrorResponse(
        fields: ['email' => 'E-mail da conta.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function forgot(
        #[MapRequestPayload] PasswordForgotRequest $request,
        Request $httpRequest,
    ): Response {
        $this->rateLimitGuard->assertWithin(
            $this->forgotLimiter,
            mb_strtolower(trim($request->email)).'|'.($httpRequest->getClientIp() ?? 'unknown'),
        );

        ($this->requestPasswordResetHandler)($request->email);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Redefinir a senha
     *
     * Consome o token enviado por e-mail, grava a nova senha e revoga todas as
     * sessões do usuário.
     */
    #[Route('/auth/password/reset', name: 'app_auth_password_reset', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Senha redefinida.')]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['token' => 'O token é obrigatório.'],
                summary: 'Erros de Validação',
                description: 'O payload de reset contém dados inválidos.',
            ),
            'PASSWORD_RESET_TOKEN_INVALID' => new ApiErrorExample(
                message: 'O token de redefinição de senha é inválido.',
                details: ['token'],
                summary: 'Token inválido',
                description: 'O token não existe ou já foi utilizado.',
            ),
            'PASSWORD_RESET_TOKEN_EXPIRED' => new ApiErrorExample(
                message: 'O token de redefinição de senha expirou. Solicite um novo.',
                details: ['token'],
                summary: 'Token expirado',
                description: 'O token passou do prazo de validade.',
            ),
        ],
        fields: [
            'token' => 'Token de redefinição.',
            'password' => 'Nova senha.',
        ],
    )]
    #[ForbiddenResponse(
        examples: [
            'ACCOUNT_SUSPENDED' => new ApiErrorExample(
                message: 'Esta conta está suspensa e não pode autenticar.',
                details: ['status'],
                summary: 'Conta suspensa',
                description: 'A conta do token está suspensa.',
            ),
        ],
        fields: ['status' => 'Situação da conta.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function reset(
        #[MapRequestPayload] PasswordResetRequest $request,
        Request $httpRequest,
    ): Response {
        $this->rateLimitGuard->assertWithin(
            $this->resetLimiter,
            $httpRequest->getClientIp() ?? 'unknown',
        );

        $result = ($this->resetPasswordHandler)($request->token, $request->password);

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        // Todas as famílias foram revogadas; o cookie do navegador não serve mais.
        $response->headers->setCookie($this->cookieFactory->clear());

        return $response;
    }
}
