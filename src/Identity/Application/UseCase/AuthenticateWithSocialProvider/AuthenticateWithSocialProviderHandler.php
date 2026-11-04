<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\AuthenticateWithSocialProvider;

use App\Identity\Application\DTO\TokenPair;
use App\Identity\Application\Exception\SocialAuthenticationException;
use App\Identity\Application\Service\SocialIdentityProviderRegistry;
use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserRegistered;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Log\LoggerInterface;

/**
 * Autentica via provedor social, criando ou vinculando a identidade ao usuário.
 */
final readonly class AuthenticateWithSocialProviderHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SocialIdentityProviderRegistry $providerRegistry,
        private TokenPairIssuerInterface $tokenPairIssuer,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return Result<TokenPair>
     */
    public function __invoke(AuthenticateWithSocialProviderCommand $command): Result
    {
        $provider = $command->provider()
                |> strtolower(...)
                |> IdentityProvider::tryFrom(...);

        if (null === $provider || $provider->isLocal()) {
            return Result::failure([
                IdentityErrors::socialProviderUnsupported($command->provider()),
            ]);
        }

        $providerAdapter = $this->providerRegistry->get($provider);

        if (null === $providerAdapter) {
            return Result::failure([
                IdentityErrors::socialProviderUnsupported($command->provider()),
            ]);
        }

        try {
            $identity = $providerAdapter->fetchIdentity(
                authorizationCode: $command->authorizationCode(),
                redirectUri: $command->redirectUri(),
                codeVerifier: $command->codeVerifier(),
                name: $command->name(),
            );
        } catch (SocialAuthenticationException $e) {
            $this->logger->error('Falha ao autenticar usuário com provedor social', [
                'provider' => $command->provider(),
                'exception' => $e,
            ]);

            return Result::failure([IdentityErrors::socialAuthenticationFailed()]);
        }

        if (!$identity->emailVerified) {
            return Result::failure([IdentityErrors::socialEmailNotVerified()]);
        }

        $user = $this->userRepository->findByProviderSubject($identity->provider, $identity->subject);

        if (null !== $user) {
            if (!$user->isActive()) {
                return Result::failure([IdentityErrors::accountSuspended()]);
            }

            return Result::success($this->tokenPairIssuer->issueForNewSession($user));
        }

        $user = $this->userRepository->findByEmail($identity->email);

        if (null !== $user) {
            if (!$user->isActive()) {
                return Result::failure([IdentityErrors::accountSuspended()]);
            }

            $user->linkSocialIdentity(
                provider: $identity->provider,
                subject: $identity->subject,
                email: $identity->email,
                emailVerifiedAt: Instant::now(),
            );
            $this->userRepository->save($user);

            return Result::success($this->tokenPairIssuer->issueForNewSession($user));
        }

        $user = User::registerWithProvider(
            provider: $identity->provider,
            subject: $identity->subject,
            email: $identity->email,
            name: $identity->name ?? $command->name(),
            emailVerifiedAt: Instant::now(),
        );

        $this->userRepository->save($user);

        $this->eventDispatcher->dispatch(UserRegistered::now(
            userId: $user->id()->value(),
            email: $identity->email->value(),
            cpf: null,
        ));

        return Result::success($this->tokenPairIssuer->issueForNewSession($user));
    }
}
