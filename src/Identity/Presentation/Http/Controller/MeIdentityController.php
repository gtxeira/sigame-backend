<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\UseCase\Social\LinkSocialIdentity\LinkWithFlowCommand;
use App\Identity\Application\UseCase\Social\LinkSocialIdentity\LinkWithFlowHandler;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\SocialCallbackRequest;
use App\Identity\Presentation\Http\Response\SocialFlowCookieFactory;
use App\Shared\Domain\Functional\Result;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\BadRequestResponse;
use App\Shared\Presentation\Http\OpenApi\ForbiddenResponse;
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
 * Vínculo de identidades sociais iniciado pela tela de configurações.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Minha conta')]
final class MeIdentityController extends AbstractController
{
    public function __construct(
        private readonly LinkWithFlowHandler $linkHandler,
        private readonly ResultResponder $resultResponder,
        private readonly SocialFlowCookieFactory $flowCookieFactory,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.auth_social_link')]
        private readonly RateLimiterFactoryInterface $linkLimiter,
    ) {
    }

    /**
     * Vincular identidade social
     *
     * Conclui um fluxo iniciado com Bearer em `/auth/social/{provider}/start`.
     * O vínculo é permitido mesmo quando o e-mail do provedor difere do e-mail
     * canônico; a ação é explícita do dono da conta.
     */
    #[Route('/users/me/identities/{provider}', name: 'app_me_identity_link', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[Security(name: 'bearerAuth')]
    #[OA\Parameter(
        name: 'provider',
        description: 'Provedor social. Apenas `google` está habilitado.',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', enum: ['google']),
    )]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Identidade vinculada.')]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['code' => 'O authorization code é obrigatório.'],
                summary: 'Erros de Validação',
                description: 'O payload do callback contém dados inválidos.',
            ),
            'SOCIAL_FLOW_INVALID' => new ApiErrorExample(
                message: 'O fluxo de autenticação social expirou ou é inválido. Inicie novamente.',
                details: ['state'],
                summary: 'Fluxo inválido',
                description: 'O fluxo não foi iniciado por este usuário ou expirou.',
            ),
            'SOCIAL_PROVIDER_UNSUPPORTED' => new ApiErrorExample(
                message: 'O provedor de login "apple" não é suportado.',
                details: ['provider'],
                summary: 'Provedor não suportado',
                description: 'Somente o provedor google está habilitado.',
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
            'state' => 'State do fluxo.',
            'provider' => 'Provedor social.',
            'email' => 'E-mail devolvido pelo provedor.',
        ],
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
                details: ['status'],
                summary: 'Conta suspensa',
                description: 'A conta autenticada está suspensa.',
            ),
        ],
        fields: ['status' => 'Situação da conta.'],
    )]
    #[ResourceConflictResponse(
        examples: [
            'SOCIAL_IDENTITY_ALREADY_LINKED' => new ApiErrorExample(
                message: 'Esta conta do provedor já está vinculada a outro usuário.',
                details: ['provider'],
                summary: 'Identidade já vinculada',
                description: 'A identidade social pertence a outro usuário.',
            ),
        ],
        fields: ['provider' => 'Provedor social.'],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function link(
        string $provider,
        #[MapRequestPayload] SocialCallbackRequest $request,
        Request $httpRequest,
    ): Response {
        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        $this->rateLimitGuard->assertWithin(
            $this->linkLimiter,
            $securityUser->user()->id()->value(),
        );

        $flowId = $httpRequest->cookies->get($this->flowCookieFactory->name());

        if (!is_string($flowId) || '' === $flowId) {
            $response = $this->resultResponder->failure(
                Result::failure([IdentityErrors::socialFlowInvalid()]),
            );
            $response->headers->setCookie($this->flowCookieFactory->clear());

            return $response;
        }

        $result = ($this->linkHandler)(new LinkWithFlowCommand(
            provider: $provider,
            flowId: $flowId,
            state: $request->state,
            authorizationCode: $request->code,
            userId: $securityUser->user()->id(),
        ));

        if ($result->isFailure()) {
            $response = $this->resultResponder->failure($result);
        } else {
            $response = new Response(null, Response::HTTP_NO_CONTENT);
        }

        $response->headers->setCookie($this->flowCookieFactory->clear());

        return $response;
    }
}
