<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\EmailVerification\RequestEmailVerificationHandler;
use App\Identity\Application\UseCase\EmailVerification\VerifyEmailHandler;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\EmailVerifyRequest;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\InternalErrorResponse;
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
        private readonly VerifyEmailHandler $verifyEmailHandler,
        private readonly RequestEmailVerificationHandler $requestEmailVerificationHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.auth_email_verify')]
        private readonly RateLimiterFactoryInterface $verifyLimiter,
        #[Autowire(service: 'limiter.auth_email_resend')]
        private readonly RateLimiterFactoryInterface $resendLimiter,
    ) {
    }

    /**
     * Verificar o e-mail
     *
     * Consome o token enviado por e-mail e marca o endereço canônico como
     * verificado. O token é de uso único e expira.
     */
    #[Route('/auth/email/verify', name: 'app_auth_email_verify', methods: ['POST'])]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'E-mail verificado.')]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['token' => 'O token é obrigatório.'],
                summary: 'Erros de Validação',
                description: 'O payload de verificação contém dados inválidos.',
            ),
            'EMAIL_VERIFICATION_TOKEN_INVALID' => new ApiErrorExample(
                message: 'O token de verificação de e-mail é inválido.',
                details: ['token'],
                summary: 'Token inválido',
                description: 'O token não existe ou já foi utilizado.',
            ),
            'EMAIL_VERIFICATION_TOKEN_EXPIRED' => new ApiErrorExample(
                message: 'O token de verificação de e-mail expirou. Solicite um novo.',
                details: ['token'],
                summary: 'Token expirado',
                description: 'O token passou do prazo de validade.',
            ),
        ],
        fields: ['token' => 'Token de verificação.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function verify(
        #[MapRequestPayload] EmailVerifyRequest $request,
        Request $httpRequest,
    ): Response {
        $this->rateLimitGuard->assertWithin(
            $this->verifyLimiter,
            $httpRequest->getClientIp() ?? 'unknown',
        );

        $result = ($this->verifyEmailHandler)($request->token);

        return $result->isFailure()
            ? $this->resultResponder->failure($result)
            : new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Reenviar verificação de e-mail
     *
     * Gera um novo token para o usuário autenticado, invalidando o anterior.
     * Se o e-mail já estiver verificado, a operação é idempotente.
     */
    #[Route('/auth/email/verification/resend', name: 'app_auth_email_resend', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Verificação reenviada quando aplicável.')]
    #[UnauthorizedResponse]
    #[TooManyRequestsResponse]
    #[InternalErrorResponse]
    public function resend(): Response
    {
        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        $this->rateLimitGuard->assertWithin(
            $this->resendLimiter,
            $securityUser->user()->id()->value(),
        );

        ($this->requestEmailVerificationHandler)($securityUser->user());

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
