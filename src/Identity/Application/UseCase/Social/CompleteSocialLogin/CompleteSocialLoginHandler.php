<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Social\CompleteSocialLogin;

use App\Identity\Application\DTO\IssuedLinkTicket;
use App\Identity\Application\DTO\LinkTicket;
use App\Identity\Application\DTO\SocialFlow;
use App\Identity\Application\DTO\SocialFlowPurpose;
use App\Identity\Application\DTO\SocialIdentity;
use App\Identity\Application\DTO\SocialLoginOutcome;
use App\Identity\Application\Exception\SocialAuthenticationException;
use App\Identity\Application\Service\Pkce;
use App\Identity\Application\Service\SocialFlowStoreInterface;
use App\Identity\Application\Service\SocialIdentityProviderRegistry;
use App\Identity\Application\Service\SocialLinkTicketStoreInterface;
use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserRegistered;
use App\Identity\Domain\User\Exception\EmailAlreadyExistsException;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Conclui o login social, criando a conta ou vinculando a identidade.
 *
 * Vínculo automático só é seguro quando a conta existente não possui senha
 * local. Caso contrário, devolve um ticket para o usuário autenticar e
 * confirmar o vínculo explicitamente.
 */
final readonly class CompleteSocialLoginHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SocialIdentityProviderRegistry $providerRegistry,
        private SocialFlowStoreInterface $flowStore,
        private SocialLinkTicketStoreInterface $linkTicketStore,
        private TokenPairIssuerInterface $tokenPairIssuer,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        #[Autowire(env: 'int:SOCIAL_LINK_TICKET_TTL')]
        private int $linkTicketTtlSeconds = 600,
    ) {
    }

    /**
     * @return Result<SocialLoginOutcome>
     */
    public function __invoke(CompleteSocialLoginCommand $command): Result
    {
        $provider = IdentityProvider::tryFrom(strtolower($command->provider()));

        if (null === $provider || $provider->isLocal()) {
            return Result::failure([
                IdentityErrors::socialProviderUnsupported($command->provider()),
            ]);
        }

        $flow = $this->flowStore->consume($command->flowId());

        if (!$this->isValidLoginFlow($flow, $provider, $command->state())) {
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
            $this->logger->warning('Falha ao autenticar usuário com provedor social', [
                'provider' => $provider->value,
                'reason' => $exception->getMessage(),
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

            return Result::success(SocialLoginOutcome::authenticated(
                $this->tokenPairIssuer->issueForNewSession($user),
            ));
        }

        $user = $this->userRepository->findByEmail($identity->email);

        if (null !== $user) {
            if (!$user->isActive()) {
                return Result::failure([IdentityErrors::accountSuspended()]);
            }

            if ($user->hasLocalPassword()) {
                return Result::success(SocialLoginOutcome::linkRequired($this->issueLinkTicket($identity)));
            }

            $user->linkSocialIdentity(
                provider: $identity->provider,
                subject: $identity->subject,
                email: $identity->email,
                emailVerifiedAt: Instant::fromDateTime($this->clock->now()),
            );
            $this->userRepository->save($user);

            return Result::success(SocialLoginOutcome::authenticated(
                $this->tokenPairIssuer->issueForNewSession($user),
            ));
        }

        $user = User::registerWithProvider(
            provider: $identity->provider,
            subject: $identity->subject,
            email: $identity->email,
            name: $identity->name,
            emailVerifiedAt: Instant::fromDateTime($this->clock->now()),
        );

        try {
            $this->userRepository->save($user);
        } catch (EmailAlreadyExistsException) {
            // Corrida com outro cadastro: a conta passou a existir entre a
            // consulta e o flush. O vínculo agora exige autenticação.
            return Result::success(SocialLoginOutcome::linkRequired($this->issueLinkTicket($identity)));
        }

        $this->eventDispatcher->dispatch(UserRegistered::now(
            userId: $user->id()->value(),
            email: $identity->email->value(),
            cpf: null,
        ));

        return Result::success(SocialLoginOutcome::authenticated(
            $this->tokenPairIssuer->issueForNewSession($user),
        ));
    }

    private function isValidLoginFlow(
        ?SocialFlow $flow,
        IdentityProvider $provider,
        string $state,
    ): bool {
        if (null === $flow) {
            return false;
        }

        return SocialFlowPurpose::Login === $flow->purpose
            && $flow->provider === $provider
            && hash_equals($flow->state, $state)
            && !$flow->isExpiredAt(Instant::fromDateTime($this->clock->now()));
    }

    private function issueLinkTicket(SocialIdentity $identity): IssuedLinkTicket
    {
        $expiresAt = Instant::fromDateTime(
            $this->clock->now()->modify(sprintf('+%d seconds', $this->linkTicketTtlSeconds)),
        );
        $token = Pkce::linkToken();

        $this->linkTicketStore->save(
            token: $token,
            ticket: new LinkTicket(
                provider: $identity->provider,
                subject: $identity->subject,
                email: $identity->email,
                expiresAt: $expiresAt,
            ),
            ttlSeconds: $this->linkTicketTtlSeconds,
        );

        return new IssuedLinkTicket($token, $expiresAt);
    }
}
