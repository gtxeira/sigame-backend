<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\EmailVerification\EmailVerificationRequestOutcome;
use App\Identity\Application\UseCase\EmailVerification\RequestEmailVerificationHandler;
use App\Identity\Application\UseCase\EmailVerification\VerifyEmailOtpHandler;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\EmailVerifyRequest;
use App\Shared\Domain\Functional\Result;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
use App\Shared\Presentation\Http\OpenApi\ResourceConflictResponse;
use App\Shared\Presentation\Http\OpenApi\TooManyRequestsResponse;
use App\Shared\Presentation\Http\OpenApi\UnauthorizedResponse;
use App\Shared\Presentation\Http\OpenApi\UnsupportedMediaTypeResponse;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;
use App\Shared\Presentation\Http\RateLimit\RateLimitGuard;
use App\Shared\Presentation\Http\Response\ResultResponder;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.auth_email_verify')]
        private readonly RateLimiterFactoryInterface $verifyLimiter,
        #[Autowire(service: 'limiter.auth_email_verify_ip')]
        private readonly RateLimiterFactoryInterface $verifyIpLimiter,
        #[Autowire(service: 'limiter.auth_email_resend')]
        private readonly RateLimiterFactoryInterface $resendLimiter,
    ) {
    }

    /**
     * Verificar o e-mail
     *
     * Valida o código OTP enviado por e-mail e marca o endereço canônico como
     * verificado. O código é de uso único, expira e é invalidado após 5 erros.
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
                message: 'O código de verificação de e-mail é inválido.',
                field: 'code',
                summary: 'Código inválido',
                description: 'O código não existe, já foi utilizado ou não pertence a este e-mail.',
            ),
            'EMAIL_VERIFICATION_CODE_EXPIRED' => new ApiErrorExample(
                message: 'O código de verificação de e-mail expirou. Solicite um novo.',
                field: 'code',
                summary: 'Código expirado',
                description: 'O código passou do prazo de validade.',
            ),
            'EMAIL_VERIFICATION_CODE_ATTEMPTS_EXCEEDED' => new ApiErrorExample(
                message: 'Muitas tentativas inválidas. Solicite um novo código de verificação.',
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
     * Gera um novo código e invalida o anterior. O cadastro local já enfileira
     * a confirmação automaticamente; use este endpoint quando o e-mail não
     * chegou ou quando o código expirou. Contas já verificadas não recebem novo
     * e-mail.
     */
    #[Route('/auth/email/verification/resend', name: 'app_auth_email_resend', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'E-mail de verificação enfileirado.')]
    #[UnauthorizedResponse]
    #[ResourceConflictResponse(
        examples: [
            'EMAIL_ALREADY_VERIFIED' => new ApiErrorExample(
                message: 'O e-mail já está verificado.',
                summary: 'E-mail já verificado',
                description: 'Nenhum novo e-mail é enviado quando o endereço já foi confirmado.',
            ),
        ],
    )]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function resend(): Response
    {
        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        if ($securityUser->user()->isEmailVerified()) {
            return $this->resultResponder->failure(
                Result::failure([IdentityErrors::emailAlreadyVerified()]),
            );
        }

        $this->rateLimitGuard->assertWithin(
            $this->resendLimiter,
            $securityUser->user()->id()->value(),
        );

        $outcome = ($this->requestEmailVerificationHandler)($securityUser->user());
        assert(EmailVerificationRequestOutcome::Queued === $outcome);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
