<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\EmailVerification;

use App\Identity\Application\Service\IdentityMailDispatcherInterface;
use App\Identity\Domain\IdentityToken\Entity\IdentityToken;
use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Emite e envia um token de verificação de e-mail.
 */
final readonly class RequestEmailVerificationHandler
{
    public function __construct(
        private IdentityTokenRepositoryInterface $tokenRepository,
        private IdentityMailDispatcherInterface $mailDispatcher,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        #[Autowire(env: 'int:EMAIL_VERIFICATION_TTL')]
        private int $ttlSeconds = 86400,
    ) {
    }

    public function __invoke(User $user): void
    {
        if ($user->isEmailVerified()) {
            return;
        }

        $this->tokenRepository->invalidateUnconsumed($user, IdentityTokenPurpose::EmailVerification);

        $rawToken = bin2hex(random_bytes(32));

        $expiresAt = sprintf('+%d seconds', $this->ttlSeconds)
            |> $this->clock->now()->modify(...)
            |> Instant::fromDateTime(...);

        $token = IdentityToken::create(
            user: $user,
            purpose: IdentityTokenPurpose::EmailVerification,
            tokenHash: hash('sha256', $rawToken),
            expiresAt: $expiresAt,
        );

        $this->tokenRepository->save($token);

        try {
            $this->mailDispatcher->dispatchEmailVerification(
                $user->email(),
                $rawToken,
                $expiresAt,
                $token->tokenHash(),
            );
        } catch (Throwable $exception) {
            // Falha de enfileiramento não pode derrubar o cadastro; o usuário pode reenviar.
            $this->logger->warning('Falha ao enfileirar e-mail de verificação', [
                'userId' => $user->id()->value(),
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
