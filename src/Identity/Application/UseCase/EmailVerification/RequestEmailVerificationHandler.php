<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\EmailVerification;

use App\Identity\Application\Service\IdentityMailDispatcherInterface;
use App\Identity\Application\Service\OtpCodeGeneratorInterface;
use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Emite e envia um código de verificação de e-mail.
 */
final readonly class RequestEmailVerificationHandler
{
    public function __construct(
        private OtpChallengeRepositoryInterface $challengeRepository,
        private OtpCodeGeneratorInterface $codeGenerator,
        private PasswordHasherInterface $passwordHasher,
        private IdentityMailDispatcherInterface $mailDispatcher,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        #[Autowire(env: 'int:EMAIL_VERIFICATION_OTP_TTL')]
        private int $ttlSeconds = 900,
        #[Autowire(env: 'int:EMAIL_VERIFICATION_COOLDOWN')]
        private int $cooldownSeconds = 60,
    ) {
    }

    public function __invoke(User $user): EmailVerificationRequestOutcome
    {
        if ($user->isEmailVerified()) {
            return EmailVerificationRequestOutcome::AlreadyVerified;
        }

        $now = $this->clock->now();

        if ($this->cooldownSeconds > 0 && $this->challengeRepository->hasActiveCreatedAfter(
            $user,
            OtpPurpose::EmailVerification,
            sprintf('-%d seconds', $this->cooldownSeconds)
                |> $now->modify(...)
                |> Instant::fromDateTime(...),
        )) {
            return EmailVerificationRequestOutcome::Queued;
        }

        $this->challengeRepository->invalidateActive($user, OtpPurpose::EmailVerification);

        $code = $this->codeGenerator->generate();

        $expiresAt = sprintf('+%d seconds', $this->ttlSeconds)
            |> $now->modify(...)
            |> Instant::fromDateTime(...);

        $challenge = OtpChallenge::create(
            user: $user,
            purpose: OtpPurpose::EmailVerification,
            codeHash: $this->passwordHasher->hash($code->value())->value(),
            expiresAt: $expiresAt,
        );

        $this->challengeRepository->save($challenge);

        try {
            $this->mailDispatcher->dispatchEmailVerification(
                $user->email(),
                $code->value(),
                $expiresAt,
                $challenge->id(),
            );
        } catch (Throwable $exception) {
            // Falha de enfileiramento não pode derrubar o cadastro; o usuário pode reenviar.
            $this->logger->warning('Falha ao enfileirar e-mail de verificação', [
                'userId' => $user->id()->value(),
                'reason' => $exception->getMessage(),
            ]);
        }

        return EmailVerificationRequestOutcome::Queued;
    }
}
