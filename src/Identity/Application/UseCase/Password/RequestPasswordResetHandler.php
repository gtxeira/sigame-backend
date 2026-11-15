<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Password;

use App\Identity\Application\Service\IdentityMailDispatcherInterface;
use App\Identity\Application\Service\OtpCodeGeneratorInterface;
use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
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
 * Pedidos repetidos dentro do cooldown não geram novo código nem novo e-mail.
 */
final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private OtpChallengeRepositoryInterface $challengeRepository,
        private OtpCodeGeneratorInterface $codeGenerator,
        private PasswordHasherInterface $passwordHasher,
        private IdentityMailDispatcherInterface $mailDispatcher,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        #[Autowire(env: 'int:PASSWORD_RESET_OTP_TTL')]
        private int $ttlSeconds = 600,
        #[Autowire(env: 'int:PASSWORD_RESET_COOLDOWN')]
        private int $cooldownSeconds = 60,
    ) {
    }

    public function __invoke(string $email): PasswordResetRequestOutcome
    {
        $emailResult = Email::fromString($email);

        if ($emailResult->isInvalid()) {
            return PasswordResetRequestOutcome::requested();
        }

        $user = $this->userRepository->findByEmail($emailResult->value());

        if (null === $user || !$user->isActive()) {
            return PasswordResetRequestOutcome::requested();
        }

        $now = $this->clock->now();

        if ($this->cooldownSeconds > 0) {
            $lastRequestAt = $this->challengeRepository->findActiveCreatedAt(
                $user,
                OtpPurpose::PasswordReset,
            );

            if (null !== $lastRequestAt) {
                $elapsed = $now->getTimestamp() - $lastRequestAt->timestamp();
                $remaining = $this->cooldownSeconds - $elapsed;

                if ($remaining > 0) {
                    return PasswordResetRequestOutcome::throttled($remaining);
                }
            }
        }

        $this->challengeRepository->invalidateActive($user, OtpPurpose::PasswordReset);

        $code = $this->codeGenerator->generate();

        $expiresAt = sprintf('+%d seconds', $this->ttlSeconds)
            |> $now->modify(...)
            |> Instant::fromDateTime(...);

        $challenge = OtpChallenge::create(
            user: $user,
            purpose: OtpPurpose::PasswordReset,
            codeHash: $this->passwordHasher->hash($code->value())->value(),
            expiresAt: $expiresAt,
        );

        $this->challengeRepository->save($challenge);

        try {
            $this->mailDispatcher->dispatchPasswordReset(
                $user->email(),
                $code->value(),
                $expiresAt,
                $challenge->id(),
            );
        } catch (Throwable $exception) {
            $this->consumeSilently($challenge, $user);
            $this->logger->warning('Falha ao enfileirar e-mail de reset de senha', [
                'userId' => $user->id()->value(),
                'reason' => $exception->getMessage(),
            ]);
        }

        return PasswordResetRequestOutcome::requested();
    }

    /**
     * Impede que um código não entregue continue válido após a falha de envio.
     */
    private function consumeSilently(OtpChallenge $challenge, User $user): void
    {
        try {
            $this->challengeRepository->markConsumed($challenge);
        } catch (Throwable $exception) {
            $this->logger->warning('Falha ao invalidar código após erro de e-mail de reset', [
                'userId' => $user->id()->value(),
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
