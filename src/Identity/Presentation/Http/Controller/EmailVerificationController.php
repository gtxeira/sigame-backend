<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\EmailVerification\RequestEmailVerificationHandler;
use App\Identity\Application\UseCase\EmailVerification\VerifyEmailOtpHandler;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Presentation\Http\Request\EmailResendRequest;
use App\Identity\Presentation\Http\Request\EmailVerifyRequest;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
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
 * Verificação do e-mail canônico da conta.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Verificação de e-mail')]
final class EmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly VerifyEmailOtpHandler $verifyEmailOtpHandler,
        private readonly RequestEmailVerificationHandler $requestEmailVerificationHandler,
        private readonly UserRepositoryInterface $userRepository,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.auth_email_verify')]
        private readonly RateLimiterFactoryInterface $verifyLimiter,
        #[Autowire(service: 'limiter.auth_email_verify_ip')]
        private readonly RateLimiterFactoryInterface $verifyIpLimiter,
        #[Autowire(service: 'limiter.auth_email_resend')]
        private readonly RateLimiterFactoryInterface $resendLimiter,
        #[Autowire(service: 'limiter.auth_email_resend_ip')]
        private readonly RateLimiterFactoryInterface $resendIpLimiter,
    ) {
    }

    /**
     * Verificar o e-mail
     *
     * Valida o código OTP enviado por e-mail e marca o endereço canônico como
     * verificado. O código é de uso único e expira; após 5 erros é invalidado
     * internamente, sem revelar o motivo.
     */
    #[Route('/auth/email/verify', name: 'app_auth_email_verify', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'E-mail verificado.')]
    #[ValidationErrorResponse(
        examples: [
            'FIELD_REQUIRED' => new ApiErrorExample(
                message: 'O código é obrigatório.',
                field: 'code',
                summary: 'Erros de Validação',
                description: 'O payload de verificação contém dados inválidos.',
            ),
            'EMAIL_VERIFICATION_CODE_INVALID' => new ApiErrorExample(
                message: 'O código de verificação é inválido ou expirou. Solicite um novo.',
                field: 'code',
                summary: 'Código inválido',
                description: 'O código não existe, já foi utilizado, expirou ou não pertence a este e-mail.',
            ),
        ],
        fields: [
            'email' => 'E-mail da conta.',
            'code' => 'Código de 6 dígitos.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function verify(
        #[MapRequestPayload] EmailVerifyRequest $request,
        Request $httpRequest,
    ): Response {
        $clientIp = $httpRequest->getClientIp() ?? 'unknown';
        $emailKey = mb_strtolower(trim($request->email)).'|'.$clientIp;

        $this->rateLimitGuard->assertWithin($this->verifyLimiter, $emailKey);
        $this->rateLimitGuard->assertWithin($this->verifyIpLimiter, $clientIp);

        $result = ($this->verifyEmailOtpHandler)($request->email, $request->code);

        return $result->isFailure()
            ? $this->resultResponder->failure($result)
            : new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Reenviar verificação de e-mail
     *
     * Público e sempre `204`: envia um novo código apenas quando a conta existe
     * e ainda não foi verificada. A resposta é a mesma nos demais casos, para
     * não permitir enumeração. O cadastro já enfileira a confirmação; use este
     * endpoint quando o e-mail não chegou ou o código expirou.
     */
    #[Route('/auth/email/verification/resend', name: 'app_auth_email_resend', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Solicitação processada.')]
    #[ValidationErrorResponse(
        fields: ['email' => 'E-mail da conta.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function resend(
        #[MapRequestPayload] EmailResendRequest $request,
        Request $httpRequest,
    ): Response {
        $clientIp = $httpRequest->getClientIp() ?? 'unknown';
        $emailKey = mb_strtolower(trim($request->email)).'|'.$clientIp;

        $this->rateLimitGuard->assertWithin($this->resendLimiter, $emailKey);
        $this->rateLimitGuard->assertWithin($this->resendIpLimiter, $clientIp);

        $emailResult = Email::fromString($request->email);

        if ($emailResult->isValid()) {
            /** @var Email $email */
            $email = $emailResult->value();
            $user = $this->userRepository->findByEmail($email);

            if (null !== $user && !$user->isEmailVerified()) {
                ($this->requestEmailVerificationHandler)($user);
            }
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
