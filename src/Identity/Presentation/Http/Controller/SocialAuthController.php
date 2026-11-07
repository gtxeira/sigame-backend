<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Controller;

use App\Identity\Application\DTO\SocialAuthorization;
use App\Identity\Application\DTO\SocialLoginOutcome;
use App\Identity\Application\UseCase\Social\CompleteSocialLogin\CompleteSocialLoginCommand;
use App\Identity\Application\UseCase\Social\CompleteSocialLogin\CompleteSocialLoginHandler;
use App\Identity\Application\UseCase\Social\LinkSocialIdentity\LinkWithTicketCommand;
use App\Identity\Application\UseCase\Social\LinkSocialIdentity\LinkWithTicketHandler;
use App\Identity\Application\UseCase\Social\StartSocialFlow\StartSocialFlowCommand;
use App\Identity\Application\UseCase\Social\StartSocialFlow\StartSocialFlowHandler;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Identity\Presentation\Http\Request\SocialCallbackRequest;
use App\Identity\Presentation\Http\Request\SocialLinkRequest;
use App\Identity\Presentation\Http\Request\SocialStartRequest;
use App\Identity\Presentation\Http\Response\SocialFlowCookieFactory;
use App\Identity\Presentation\Http\Response\SocialStartResponse;
use App\Identity\Presentation\Http\Response\TokenPairResponseFactory;
use App\Identity\Presentation\Http\Response\TokenResponse;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\ApiMeta;
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
use DateTimeImmutable;
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
 * Fluxo OAuth do backend: start, callback e vínculo explícito.
 */
#[Route('/api/v1/auth/social/{provider}')]
#[OA\Tag(name: 'Autenticação social')]
final class SocialAuthController extends AbstractController
{
    public function __construct(
        private readonly StartSocialFlowHandler $startHandler,
        private readonly CompleteSocialLoginHandler $completeHandler,
        private readonly LinkWithTicketHandler $linkHandler,
        private readonly ResultResponder $resultResponder,
        private readonly TokenPairResponseFactory $tokenPairResponseFactory,
        private readonly SocialFlowCookieFactory $flowCookieFactory,
        private readonly RateLimitGuard $rateLimitGuard,
        #[Autowire(service: 'limiter.auth_social_start')]
        private readonly RateLimiterFactoryInterface $startLimiter,
        #[Autowire(service: 'limiter.auth_social_callback')]
        private readonly RateLimiterFactoryInterface $callbackLimiter,
        #[Autowire(service: 'limiter.auth_social_link')]
        private readonly RateLimiterFactoryInterface $linkLimiter,
    ) {
    }

    /**
     * Iniciar o fluxo de login social
     *
     * Gera state e PKCE no servidor, guarda o fluxo em cache e devolve a URL
     * de autorização. Com Bearer válido, o fluxo nasce com a finalidade de
     * vínculo e o callback deve ser enviado à rota de identidades.
     */
    #[Route('/start', name: 'app_auth_social_start', methods: ['POST'])]
    #[OA\Parameter(
        name: 'provider',
        description: 'Provedor social. Apenas `google` está habilitado.',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', enum: ['google']),
    )]
    #[SuccessResponse(
        dataModel: SocialStartResponse::class,
        description: 'Fluxo criado; redirecione o navegador para authorizationUrl.',
    )]
    #[ValidationErrorResponse(
        examples: [
            'VALIDATION_ERROR' => new ApiErrorExample(
                message: 'Um ou mais erros de validação foram encontrados.',
                details: ['client' => 'O cliente é obrigatório.'],
                summary: 'Erros de Validação',
                description: 'O payload de início contém dados inválidos.',
            ),
            'SOCIAL_PROVIDER_UNSUPPORTED' => new ApiErrorExample(
                message: 'O provedor de login "apple" não é suportado.',
                details: ['provider'],
                summary: 'Provedor não suportado',
                description: 'Somente o provedor google está habilitado.',
            ),
            'SOCIAL_CLIENT_UNSUPPORTED' => new ApiErrorExample(
                message: 'O cliente de login "mobile" não é suportado.',
                details: ['client'],
                summary: 'Cliente não suportado',
                description: 'O cliente não está configurado neste ambiente.',
            ),
        ],
        fields: [
            'client' => 'Tipo de cliente OAuth.',
            'provider' => 'Provedor social.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function start(
        string $provider,
        #[MapRequestPayload] SocialStartRequest $request,
        Request $httpRequest,
    ): JsonResponse {
        $securityUser = $this->getUser();
        $userId = $securityUser instanceof SecurityUser ? $securityUser->user()->id() : null;

        $this->rateLimitGuard->assertWithin(
            $this->startLimiter,
            mb_strtolower($provider).'|'.($httpRequest->getClientIp() ?? 'unknown'),
        );

        $result = ($this->startHandler)(new StartSocialFlowCommand(
            provider: $provider,
            client: $request->client,
            userId: $userId,
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        /** @var SocialAuthorization $authorization */
        $authorization = $result->value();

        $response = $this->resultResponder->success(
            $result->map(static fn (SocialAuthorization $value): SocialStartResponse => new SocialStartResponse(
                authorizationUrl: $value->authorizationUrl,
                state: $value->state,
                expiresIn: $value->expiresIn,
            )),
        );
        $response->headers->setCookie($this->flowCookieFactory->create(
            flowId: $authorization->flowId,
            expiresAt: Instant::fromDateTime(
                (new DateTimeImmutable())->modify(sprintf('+%d seconds', $authorization->expiresIn)),
            ),
        ));

        return $response;
    }

    /**
     * Concluir o login social
     *
     * Valida o state contra o cookie de fluxo, troca o code com o redirect URI
     * e o PKCE mantidos no servidor e emite o par de tokens. Quando a conta já
     * existe com senha local, responde 409 com um ticket de vínculo em
     * `meta.link`.
     */
    #[Route('/callback', name: 'app_auth_social_callback', methods: ['POST'])]
    #[OA\Parameter(
        name: 'provider',
        description: 'Provedor social. Apenas `google` está habilitado.',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', enum: ['google']),
    )]
    #[SuccessResponse(
        dataModel: TokenResponse::class,
        description: 'Autenticado; o refresh token é entregue no cookie HttpOnly.',
    )]
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
                description: 'O cookie de fluxo, o state ou a validade não conferem.',
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
                description: 'A conta vinculada ao provedor está suspensa.',
            ),
        ],
        fields: ['status' => 'Situação da conta.'],
    )]
    #[ResourceConflictResponse(
        examples: [
            'SOCIAL_ACCOUNT_LINK_REQUIRED' => new ApiErrorExample(
                message: 'Já existe uma conta cadastrada com este e-mail. Faça login com sua senha para vincular sua conta do Google.',
                details: ['email'],
                summary: 'Vínculo explícito necessário',
                description: 'A conta existente possui senha local. O ticket de vínculo vem em meta.link.',
            ),
        ],
        fields: ['email' => 'E-mail da conta existente.'],
        meta: new ApiMeta(
            properties: [
                new OA\Property(
                    property: 'link',
                    description: 'Ticket para vincular a conta social após o login local.',
                    required: ['token', 'expiresAt'],
                    properties: [
                        new OA\Property(
                            property: 'token',
                            description: 'Ticket de vínculo, de uso único.',
                            type: 'string',
                            example: 'a1b2c3...',
                            maxLength: 128,
                        ),
                        new OA\Property(
                            property: 'expiresAt',
                            description: 'Instante de expiração do ticket, em ISO 8601 com milissegundos.',
                            type: 'string',
                            format: 'date-time',
                            example: '2026-11-06T12:00:00.000+00:00',
                        ),
                    ],
                    type: 'object',
                ),
            ],
            required: ['link'],
        ),
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function callback(
        string $provider,
        #[MapRequestPayload] SocialCallbackRequest $request,
        Request $httpRequest,
    ): JsonResponse {
        $this->rateLimitGuard->assertWithin(
            $this->callbackLimiter,
            mb_strtolower($provider).'|'.($httpRequest->getClientIp() ?? 'unknown'),
        );

        $flowId = $httpRequest->cookies->get($this->flowCookieFactory->name());

        if (!is_string($flowId) || '' === $flowId) {
            $response = $this->resultResponder->failure(
                Result::failure([IdentityErrors::socialFlowInvalid()]),
            );
            $response->headers->setCookie($this->flowCookieFactory->clear());

            return $response;
        }

        $result = ($this->completeHandler)(new CompleteSocialLoginCommand(
            provider: $provider,
            flowId: $flowId,
            state: $request->state,
            authorizationCode: $request->code,
        ));

        if ($result->isFailure()) {
            $response = $this->resultResponder->failure($result);
        } else {
            /** @var SocialLoginOutcome $outcome */
            $outcome = $result->value();

            if ($outcome->requiresLink()) {
                $ticket = $outcome->linkTicket;
                assert(null !== $ticket);

                $response = $this->resultResponder->failureWithMeta(
                    Result::failure([IdentityErrors::socialAccountLinkRequired()]),
                    ['link' => [
                        'token' => $ticket->token,
                        'expiresAt' => $ticket->expiresAt->toIsoStringWithMilliseconds(),
                    ]],
                );
            } else {
                $tokens = $outcome->tokens;
                assert(null !== $tokens);

                $response = $this->tokenPairResponseFactory->fromResult(Result::success($tokens));
            }
        }

        $response->headers->setCookie($this->flowCookieFactory->clear());

        return $response;
    }

    /**
     * Vincular conta social pelo ticket
     *
     * Usa o ticket recebido no 409 após o login local. Exige Bearer e confere
     * se o e-mail do ticket é o mesmo da conta autenticada.
     */
    #[Route('/link', name: 'app_auth_social_link', methods: ['POST'])]
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
                details: ['linkToken' => 'O linkToken é obrigatório.'],
                summary: 'Erros de Validação',
                description: 'O payload de vínculo contém dados inválidos.',
            ),
            'SOCIAL_LINK_TOKEN_INVALID' => new ApiErrorExample(
                message: 'O vínculo de conta expirou ou é inválido. Inicie o login novamente.',
                details: ['linkToken'],
                summary: 'Ticket inválido',
                description: 'O ticket expirou ou já foi utilizado.',
            ),
            'SOCIAL_PROVIDER_UNSUPPORTED' => new ApiErrorExample(
                message: 'O provedor de login "apple" não é suportado.',
                details: ['provider'],
                summary: 'Provedor não suportado',
                description: 'Somente o provedor google está habilitado.',
            ),
        ],
        fields: [
            'linkToken' => 'Ticket de vínculo.',
            'provider' => 'Provedor social.',
        ],
    )]
    #[UnauthorizedResponse]
    #[ResourceConflictResponse(
        examples: [
            'SOCIAL_LINK_EMAIL_MISMATCH' => new ApiErrorExample(
                message: 'A conta do provedor informada não corresponde ao e-mail desta conta.',
                details: ['email'],
                summary: 'E-mail divergente',
                description: 'O ticket foi emitido para outra conta.',
            ),
            'SOCIAL_IDENTITY_ALREADY_LINKED' => new ApiErrorExample(
                message: 'Esta conta do provedor já está vinculada a outro usuário.',
                details: ['provider'],
                summary: 'Identidade já vinculada',
                description: 'A identidade social pertence a outro usuário.',
            ),
        ],
        fields: [
            'email' => 'E-mail da conta.',
            'provider' => 'Provedor social.',
        ],
    )]
    #[TooManyRequestsResponse]
    #[BadRequestResponse]
    #[UnsupportedMediaTypeResponse]
    #[InternalErrorResponse]
    public function link(
        string $provider,
        #[MapRequestPayload] SocialLinkRequest $request,
    ): Response {
        $securityUser = $this->getUser();
        assert($securityUser instanceof SecurityUser);

        $this->rateLimitGuard->assertWithin(
            $this->linkLimiter,
            $securityUser->user()->id()->value(),
        );

        $result = ($this->linkHandler)(new LinkWithTicketCommand(
            provider: $provider,
            linkToken: $request->linkToken,
            userId: $securityUser->user()->id(),
        ));

        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
