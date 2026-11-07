<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Password;

use App\Identity\Application\Service\IdentityMailDispatcherInterface;
use App\Identity\Domain\IdentityToken\Entity\IdentityToken;
use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Solicita o reset de senha sem revelar se o e-mail existe.
 *
 * Pedidos repetidos dentro do cooldown não geram novo token nem novo e-mail.
 */
final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private IdentityTokenRepositoryInterface $tokenRepository,
        private IdentityMailDispatcherInterface $mailDispatcher,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        #[Autowire(env: 'int:PASSWORD_RESET_TTL')]
        private int $ttlSeconds = 1800,
        #[Autowire(env: 'int:PASSWORD_RESET_COOLDOWN')]
        private int $cooldownSeconds = 60,
    ) {
    }

    public function __invoke(string $email): void
    {
        $emailResult = Email::fromString($email);

        if ($emailResult->isInvalid()) {
            return;
        }

        $user = $this->userRepository->findByEmail($emailResult->value());

        if (null === $user || !$user->isActive()) {
            return;
        }

        $now = $this->clock->now();

        if ($this->cooldownSeconds > 0 && $this->tokenRepository->hasUnconsumedCreatedAfter(
            $user,
            IdentityTokenPurpose::PasswordReset,
            Instant::fromDateTime($now->modify(sprintf('-%d seconds', $this->cooldownSeconds))),
        )) {
            return;
        }

        $this->tokenRepository->invalidateUnconsumed($user, IdentityTokenPurpose::PasswordReset);

        $rawToken = bin2hex(random_bytes(32));

        $expiresAt = sprintf('+%d seconds', $this->ttlSeconds)
            |> $now->modify(...)
            |> Instant::fromDateTime(...);

        $token = IdentityToken::create(
            user: $user,
            purpose: IdentityTokenPurpose::PasswordReset,
            tokenHash: hash('sha256', $rawToken),
            expiresAt: $expiresAt,
        );

        $this->tokenRepository->save($token);

        try {
            $this->mailDispatcher->dispatchPasswordReset(
                $user->email(),
                $rawToken,
                $expiresAt,
                $token->tokenHash(),
            );
        } catch (Throwable $exception) {
            $this->consumeSilently($token, $user);
            $this->logger->warning('Falha ao enfileirar e-mail de reset de senha', [
                'userId' => $user->id()->value(),
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Impede que um token não entregue continue válido após a falha de envio.
     */
    private function consumeSilently(IdentityToken $token, User $user): void
    {
        try {
            $this->tokenRepository->markConsumed($token);
        } catch (Throwable $exception) {
            $this->logger->warning('Falha ao invalidar token após erro de e-mail de reset', [
                'userId' => $user->id()->value(),
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
