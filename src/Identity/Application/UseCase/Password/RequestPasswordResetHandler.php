<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Password;

use App\Identity\Application\Service\IdentityMailerInterface;
use App\Identity\Domain\IdentityToken\Entity\IdentityToken;
use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Solicita o reset de senha sem revelar se o e-mail existe.
 */
final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private IdentityTokenRepositoryInterface $tokenRepository,
        private IdentityMailerInterface $mailer,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        #[Autowire(env: 'int:PASSWORD_RESET_TTL')]
        private int $ttlSeconds = 1800,
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

        $this->tokenRepository->invalidateUnconsumed($user, IdentityTokenPurpose::PasswordReset);

        $rawToken = bin2hex(random_bytes(32));

        $expiresAt = sprintf('+%d seconds', $this->ttlSeconds)
            |> $this->clock->now()->modify(...)
            |> Instant::fromDateTime(...);

        IdentityToken::create(
            user: $user,
            purpose: IdentityTokenPurpose::PasswordReset,
            tokenHash: hash('sha256', $rawToken),
            expiresAt: $expiresAt,
        ) |> $this->tokenRepository->save(...);

        try {
            $this->mailer->sendPasswordReset($user->email(), $rawToken, $expiresAt);
        } catch (Throwable $exception) {
            $this->logger->warning('Falha ao enviar e-mail de reset de senha', [
                'userId' => $user->id()->value(),
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
