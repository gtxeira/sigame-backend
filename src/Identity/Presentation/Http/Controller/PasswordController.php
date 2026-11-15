<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\Password\RequestPasswordResetHandler;
use App\Identity\Application\UseCase\Password\ResetPasswordHandler;
use App\Identity\Application\UseCase\Password\VerifyPasswordResetOtpHandler;
use App\Identity\Presentation\Http\Request\PasswordForgotRequest;
use App\Identity\Presentation\Http\Request\PasswordResetOtpRequest;
use App\Identity\Presentation\Http\Request\PasswordResetRequest;
use App\Identity\Presentation\Http\Response\PasswordResetTicketResponse;
use App\Identity\Presentation\Http\Response\RefreshTokenCookieFactory;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
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
 * Recuperação de senha para quem não está autenticado.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Senha')]
final class PasswordController extends AbstractController
{
    public function __construct(
        private readonly RequestPasswordResetHandler $requestPasswordResetHandler,
        private readonly VerifyPasswordResetOtpHandler $verifyPasswordResetOtpHandler,
        private readonly ResetPasswordHandler $resetPasswordHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RefreshTokenCookieFactory $cookieFactory,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.auth_password_forgot')]
        private readonly RateLimiterFactoryInterface $forgotLimiter,
        #[Autowire(service: 'limiter.auth_password_forgot_ip')]
        private readonly RateLimiterFactoryInterface $forgotIpLimiter,
        #[Autowire(service: 'limiter.auth_password_otp_verify')]
        private readonly RateLimiterFactoryInterface $otpVerifyLimiter,
        #[Autowire(service: 'limiter.auth_password_otp_verify_ip')]
        private readonly RateLimiterFactoryInterface $otpVerifyIpLimiter,
        #[Autowire(service: 'limiter.auth_password_reset')]
        private readonly RateLimiterFactoryInterface $resetLimiter,
    ) {
    }

    /**
     * Solicitar redefinição de senha
     *
     * Envia um código de uso único para o e-mail canônico, quando a conta
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
        $clientIp = $httpRequest->getClientIp() ?? 'unknown';

        $this->rateLimitGuard->assertWithin(
            $this->forgotLimiter,
            mb_strtolower(trim($request->email)).'|'.$clientIp,
        );
        $this->rateLimitGuard->assertWithin($this->forgotIpLimiter, $clientIp);

        ($this->requestPasswordResetHandler)($request->email);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Validar o código de redefinição
     *
     * Valida o código enviado por e-mail e devolve um ticket temporário de uso
     * único que autoriza a troca de senha. O código é inválido após 5 erros.
     */
    #[Route('/auth/password/otp/verify', name: 'app_auth_password_otp_verify', methods: ['POST'])]
    #[SuccessResponse(
        dataModel: PasswordResetTicketResponse::class,
        description: 'Código validado; use o ticket para redefinir a senha.',
    )]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'O código é obrigatório.',
                field: 'code',
                summary: 'Erros de Validação',
                description: 'O payload de validação contém dados inválidos.',
            ),
            'PASSWORD_RESET_CODE_INVALID' => new ApiErrorExample(
                message: 'O código de redefinição de senha é inválido.',
                field: 'code',
                summary: 'Código inválido',
                description: 'O código não existe, já foi utilizado ou não pertence a este e-mail.',
            ),
            'PASSWORD_RESET_CODE_EXPIRED' => new ApiErrorExample(
                message: 'O código de redefinição de senha expirou. Solicite um novo.',
                field: 'code',
                summary: 'Código expirado',
                description: 'O código passou do prazo de validade.',
            ),
            'PASSWORD_RESET_CODE_ATTEMPTS_EXCEEDED' => new ApiErrorExample(
                message: 'Muitas tentativas inválidas. Solicite um novo código de redefinição.',
                field: 'code',
                summary: 'Tentativas excedidas',
                description: 'O código foi invalidado após 5 tentativas erradas.',
            ),
        ],
        fields: [
            'email' => 'E-mail da conta.',
            'code' => 'Código de 6 dígitos.',
        ],
    )]
    #[ForbiddenResponse(
        examples: [
            'ACCOUNT_SUSPENDED' => new ApiErrorExample(
                message: 'Esta conta está suspensa e não pode autenticar.',
                field: 'status',
                summary: 'Conta suspensa',
                description: 'A conta do e-mail está suspensa.',
            ),
        ],
        fields: ['status' => 'Situação da conta.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function otpVerify(
        #[MapRequestPayload] PasswordResetOtpRequest $request,
        Request $httpRequest,
    ): JsonResponse {
        $clientIp = $httpRequest->getClientIp() ?? 'unknown';
        $emailKey = mb_strtolower(trim($request->email)).'|'.$clientIp;

        $this->rateLimitGuard->assertWithin($this->otpVerifyLimiter, $emailKey);
        $this->rateLimitGuard->assertWithin($this->otpVerifyIpLimiter, $clientIp);

        $result = ($this->verifyPasswordResetOtpHandler)($request->email, $request->code);

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return $this->resultResponder->success(
            $result->map(PasswordResetTicketResponse::fromIssued(...)),
        );
    }

    /**
     * Redefinir a senha
     *
     * Consome o ticket temporário, grava a nova senha e revoga todas as
     * sessões do usuário.
     */
    #[Route('/auth/password/reset', name: 'app_auth_password_reset', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Senha redefinida.')]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'A autorização de redefinição é obrigatória.',
                field: 'resetToken',
                summary: 'Erros de Validação',
                description: 'O payload de reset contém dados inválidos.',
            ),
            'PASSWORD_RESET_TICKET_INVALID' => new ApiErrorExample(
                message: 'A autorização para redefinir a senha é inválida. Solicite um novo código.',
                field: 'resetToken',
                summary: 'Autorização inválida',
                description: 'O ticket não existe ou já foi utilizado.',
            ),
            'PASSWORD_RESET_TICKET_EXPIRED' => new ApiErrorExample(
                message: 'A autorização para redefinir a senha expirou. Solicite um novo código.',
                field: 'resetToken',
                summary: 'Autorização expirada',
                description: 'O ticket passou do prazo de validade.',
            ),
        ],
        fields: [
            'resetToken' => 'Ticket temporário de redefinição.',
            'password' => 'Nova senha.',
        ],
    )]
    #[ForbiddenResponse(
        examples: [
            'ACCOUNT_SUSPENDED' => new ApiErrorExample(
                message: 'Esta conta está suspensa e não pode autenticar.',
                field: 'status',
                summary: 'Conta suspensa',
                description: 'A conta do ticket está suspensa.',
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

        $result = ($this->resetPasswordHandler)($request->resetToken, $request->password);

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        // Todas as famílias foram revogadas; o cookie do navegador não serve mais.
        $response->headers->setCookie($this->cookieFactory->clear());

        return $response;
    }
}
