<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\Password\ChangePasswordHandler;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\PasswordChangeRequest;
use App\Identity\Presentation\Http\Response\RefreshTokenCookieFactory;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Troca de senha para o usuário autenticado.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Senha')]
final class MePasswordController extends AbstractController
{
    public function __construct(
        private readonly ChangePasswordHandler $changePasswordHandler,
        private readonly ResultResponder $resultResponder,
        private readonly RefreshTokenCookieFactory $cookieFactory,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.auth_password_change')]
        private readonly RateLimiterFactoryInterface $changeLimiter,
    ) {
    }

    /**
     * Alterar a senha
     *
     * Exige a senha atual, grava a nova e revoga todas as sessões. O cliente
     * precisa autenticar novamente depois da troca.
     */
    #[Route('/users/me/password', name: 'app_me_password_change', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Senha alterada; todas as sessões foram revogadas.')]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['currentPassword' => 'A senha atual é obrigatória.'],
                summary: 'Erros de Validação',
                description: 'O payload de troca contém dados inválidos.',
            ),
            'PASSWORD_CURRENT_INVALID' => new ApiErrorExample(
                message: 'A senha atual informada é inválida.',
                details: ['currentPassword'],
                summary: 'Senha atual inválida',
                description: 'A senha atual não confere; a senha não foi alterada.',
            ),
        ],
        fields: [
            'currentPassword' => 'Senha atual.',
            'newPassword' => 'Nova senha.',
        ],
    )]
    #[UnauthorizedResponse]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function change(#[MapRequestPayload] PasswordChangeRequest $request): Response
    {
        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        $this->rateLimitGuard->assertWithin(
            $this->changeLimiter,
            $securityUser->user()->id()->value(),
        );

        $result = ($this->changePasswordHandler)(
            user: $securityUser->user(),
            currentPassword: $request->currentPassword,
            newPassword: $request->newPassword,
        );

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookieFactory->clear());

        return $response;
    }
}
