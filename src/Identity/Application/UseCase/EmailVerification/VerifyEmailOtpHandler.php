<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\EmailVerification;

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpCode;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\User\Event\UserEmailVerified;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Valida o código OTP e marca o e-mail canônico como verificado.
 */
final readonly class VerifyEmailOtpHandler
{
    public function __construct(
        private OtpChallengeRepositoryInterface $challengeRepository,
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
        #[Autowire(env: 'int:IDENTITY_OTP_MAX_ATTEMPTS')]
        private int $maxAttempts = 5,
    ) {
    }

    /**
     * @return Result<bool>
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
            return Result::failure([IdentityErrors::emailVerificationCodeInvalid()]);
        }

        $challenge = $this->challengeRepository->findActive($user, OtpPurpose::EmailVerification);

        if (null === $challenge || $challenge->isConsumed()) {
            return Result::failure([IdentityErrors::emailVerificationCodeInvalid()]);
        }

        $now = Instant::fromDateTime($this->clock->now());

        if ($challenge->isExpiredAt($now)) {
            return Result::failure([IdentityErrors::emailVerificationCodeExpired()]);
        }

        if ($challenge->hasReachedAttemptLimit($this->maxAttempts)) {
            return Result::failure([IdentityErrors::emailVerificationCodeAttemptsExceeded()]);
        }

        if (!$this->passwordHasher->verify(
            $codeValue->value(),
            HashedPassword::fromHash($challenge->codeHash()),
        )) {
            $attempts = $this->challengeRepository->recordFailedAttempt($challenge, $this->maxAttempts);

            return Result::failure([
                $attempts >= $this->maxAttempts
                    ? IdentityErrors::emailVerificationCodeAttemptsExceeded()
                    : IdentityErrors::emailVerificationCodeInvalid(),
            ]);
        }

        if (!$this->challengeRepository->markConsumed($challenge)) {
            return Result::failure([IdentityErrors::emailVerificationCodeInvalid()]);
        }

        if (!$user->isEmailVerified()) {
            $user->verifyEmail($now);
            $this->userRepository->save($user);

            $this->eventDispatcher->dispatch(UserEmailVerified::now(
                userId: $user->id()->value(),
                email: $user->email()->value(),
            ));
        }

        return Result::success(true);
    }
}
