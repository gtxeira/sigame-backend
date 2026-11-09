<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Social\LinkSocialIdentity;

use App\Identity\Application\DTO\SocialFlowPurpose;
use App\Identity\Application\Exception\SocialAuthenticationException;
use App\Identity\Application\Service\SocialFlowStoreInterface;
use App\Identity\Application\Service\SocialIdentityProviderRegistry;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Exception\SocialIdentityAlreadyLinkedException;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Conclui o vínculo iniciado com Bearer na tela de configurações.
 */
final readonly class LinkWithFlowHandler
{
    public function __construct(
        private SocialFlowStoreInterface $flowStore,
        private SocialIdentityProviderRegistry $providerRegistry,
        private UserRepositoryInterface $userRepository,
        private LoggerInterface $logger,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return Result<bool>
     */
    public function __invoke(LinkWithFlowCommand $command): Result
    {
        $provider = IdentityProvider::tryFrom(strtolower($command->provider()));

        if (null === $provider || $provider->isLocal()) {
            return Result::failure([
                IdentityErrors::socialProviderUnsupported($command->provider()),
            ]);
        }

        $flow = $this->flowStore->consume($command->flowId());

        if (
            null === $flow
            || SocialFlowPurpose::Link !== $flow->purpose
            || $flow->provider !== $provider
            || !hash_equals($flow->state, $command->state())
            || $flow->isExpiredAt(Instant::fromDateTime($this->clock->now()))
            || null === $flow->userId
            || !$flow->userId->equals($command->userId())
        ) {
            return Result::failure([IdentityErrors::socialFlowInvalid()]);
        }

        $adapter = $this->providerRegistry->get($provider);

        if (null === $adapter) {
            return Result::failure([
                IdentityErrors::socialProviderUnsupported($command->provider()),
            ]);
        }

        try {
            $identity = $adapter->fetchIdentity(
                authorizationCode: $command->authorizationCode(),
                redirectUri: $flow->redirectUri,
                codeVerifier: $flow->codeVerifier,
            );
        } catch (SocialAuthenticationException $exception) {
            $this->logger->warning('Falha ao vincular identidade social', [
                'provider' => $provider->value,
                'reason' => $exception->getMessage(),
            ]);

            return Result::failure([IdentityErrors::socialAuthenticationFailed()]);
        }

        if (!$identity->emailVerified) {
            return Result::failure([IdentityErrors::socialEmailNotVerified()]);
        }

        $user = $this->userRepository->findById($command->userId());

        if (null === $user) {
            return Result::failure([IdentityErrors::socialFlowInvalid()]);
        }

        if (!$user->isActive()) {
            return Result::failure([IdentityErrors::accountSuspended()]);
        }

        $existing = $this->userRepository->findByProviderSubject($identity->provider, $identity->subject);

        if (null !== $existing && !$existing->id()->equals($user->id())) {
            return Result::failure([IdentityErrors::socialIdentityAlreadyLinked()]);
        }

        if (null === $existing) {
            $user->linkSocialIdentity(
                provider: $identity->provider,
                subject: $identity->subject,
                email: $identity->email,
                emailVerifiedAt: Instant::fromDateTime($this->clock->now()),
            );

            try {
                $this->userRepository->save($user);
            } catch (SocialIdentityAlreadyLinkedException) {
                return Result::failure([IdentityErrors::socialIdentityAlreadyLinked()]);
            }
        }

        return Result::success(true);
    }
}
