<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Password;

use App\Identity\Application\DTO\IssuedPasswordResetTicket;
use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpCode;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\PasswordReset\Entity\PasswordResetTicket;
use App\Identity\Domain\PasswordReset\Repository\PasswordResetTicketRepositoryInterface;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Valida o código OTP e emite o ticket temporário que autoriza o reset.
 */
final readonly class VerifyPasswordResetOtpHandler
{
    public function __construct(
        private OtpChallengeRepositoryInterface $challengeRepository,
        private PasswordResetTicketRepositoryInterface $ticketRepository,
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
        #[Autowire(env: 'int:IDENTITY_OTP_MAX_ATTEMPTS')]
        private int $maxAttempts = 5,
        #[Autowire(env: 'int:PASSWORD_RESET_TICKET_TTL')]
        private int $ticketTtlSeconds = 300,
    ) {
    }

    /**
     * @return Result<IssuedPasswordResetTicket>
     */
    public function __invoke(string $email, string $code): Result
    {
        $emailResult = Email::fromString($email);

        if ($emailResult->isInvalid()) {
            return Result::failure($emailResult->errors());
        }

        $codeResult = OtpCode::fromString($code);

        if ($codeResult->isInvalid()) {
            return Result::failure($codeResult->errors());
        }

        /** @var Email $emailValue */
        $emailValue = $emailResult->value();
        /** @var OtpCode $codeValue */
        $codeValue = $codeResult->value();

        $user = $this->userRepository->findByEmail($emailValue);

        if (null === $user) {
            return Result::failure([IdentityErrors::passwordResetCodeInvalid()]);
        }

        if (!$user->isActive()) {
            return Result::failure([IdentityErrors::accountSuspended()]);
        }

        $challenge = $this->challengeRepository->findActive($user, OtpPurpose::PasswordReset);

        if (null === $challenge || $challenge->isConsumed()) {
            return Result::failure([IdentityErrors::passwordResetCodeInvalid()]);
        }

        $now = Instant::fromDateTime($this->clock->now());

        if ($challenge->isExpiredAt($now)) {
            return Result::failure([IdentityErrors::passwordResetCodeExpired()]);
        }

        if ($challenge->hasReachedAttemptLimit($this->maxAttempts)) {
            return Result::failure([IdentityErrors::passwordResetCodeAttemptsExceeded()]);
        }

        if (!$this->passwordHasher->verify(
            $codeValue->value(),
            HashedPassword::fromHash($challenge->codeHash()),
        )) {
            $attempts = $this->challengeRepository->recordFailedAttempt($challenge, $this->maxAttempts);

            return Result::failure([
                $attempts >= $this->maxAttempts
                    ? IdentityErrors::passwordResetCodeAttemptsExceeded()
                    : IdentityErrors::passwordResetCodeInvalid(),
            ]);
        }

        if (!$this->challengeRepository->markConsumed($challenge)) {
            return Result::failure([IdentityErrors::passwordResetCodeInvalid()]);
        }

        /** @var User $user */
        $user = $challenge->user();

        $rawTicket = bin2hex(random_bytes(32));

        $expiresAt = sprintf('+%d seconds', $this->ticketTtlSeconds)
            |> $this->clock->now()->modify(...)
            |> Instant::fromDateTime(...);

        $this->ticketRepository->invalidateActive($user);

        $ticket = PasswordResetTicket::create(
            user: $user,
            challenge: $challenge,
            tokenHash: hash('sha256', $rawTicket),
            expiresAt: $expiresAt,
        );

        $this->ticketRepository->save($ticket);

        return Result::success(new IssuedPasswordResetTicket(
            rawToken: $rawTicket,
            expiresIn: $this->ticketTtlSeconds,
        ));
    }
}
