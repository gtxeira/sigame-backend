<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Social\StartSocialFlow;

use App\Identity\Application\DTO\SocialAuthorization;
use App\Identity\Application\DTO\SocialFlow;
use App\Identity\Application\DTO\SocialFlowPurpose;
use App\Identity\Application\Service\Pkce;
use App\Identity\Application\Service\SocialAuthorizationUrlBuilderInterface;
use App\Identity\Application\Service\SocialFlowStoreInterface;
use App\Identity\Application\Service\SocialIdentityProviderRegistry;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Cria o fluxo OAuth no servidor e devolve a URL de autorização.
 */
final readonly class StartSocialFlowHandler
{
    public function __construct(
        private SocialIdentityProviderRegistry $providerRegistry,
        private SocialAuthorizationUrlBuilderInterface $urlBuilder,
        private SocialFlowStoreInterface $flowStore,
        private ClockInterface $clock,
        #[Autowire(env: 'int:OAUTH_FLOW_TTL')]
        private int $flowTtlSeconds = 600,
    ) {
    }

    /**
     * @return Result<SocialAuthorization>
     */
    public function __invoke(StartSocialFlowCommand $command): Result
    {
        $provider = IdentityProvider::tryFrom(strtolower($command->provider()));

        if (null === $provider || $provider->isLocal() || null === $this->providerRegistry->get($provider)) {
            return Result::failure([
                IdentityErrors::socialProviderUnsupported($command->provider()),
            ]);
        }

        $redirectUri = $this->urlBuilder->redirectUri($provider, $command->client());

        if (null === $redirectUri) {
            return Result::failure([
                IdentityErrors::socialClientUnsupported($command->client()),
            ]);
        }

        $state = Pkce::state();
        $codeVerifier = Pkce::verifier();
        $flow = new SocialFlow(
            id: Pkce::flowId(),
            provider: $provider,
            client: $command->client(),
            redirectUri: $redirectUri,
            codeVerifier: $codeVerifier,
            purpose: null === $command->userId() ? SocialFlowPurpose::Login : SocialFlowPurpose::Link,
            userId: $command->userId(),
            state: $state,
            expiresAt: Instant::fromDateTime($this->clock->now()->modify(sprintf('+%d seconds', $this->flowTtlSeconds))),
        );

        $this->flowStore->save($flow, $this->flowTtlSeconds);

        return Result::success(new SocialAuthorization(
            flowId: $flow->id,
            authorizationUrl: $this->urlBuilder->authorizationUrl(
                provider: $provider,
                redirectUri: $redirectUri,
                state: $state,
                codeChallenge: Pkce::challenge($codeVerifier),
            ),
            state: $state,
            expiresIn: $this->flowTtlSeconds,
        ));
    }
}
