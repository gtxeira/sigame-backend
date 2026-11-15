<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\EmailVerification;

use App\Identity\Application\Service\DummyPasswordHash;
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
        $challenge = null === $user
            ? null
            : $this->challengeRepository->findActive($user, OtpPurpose::EmailVerification);

        $now = Instant::fromDateTime($this->clock->now());
        $usable = null !== $challenge
            && !$challenge->isConsumed()
            && !$challenge->isExpiredAt($now)
            && !$challenge->hasReachedAttemptLimit($this->maxAttempts);

        // Sempre uma verificação: sem desafio utilizável, usa o hash dummy para
        // que o tempo de resposta não revele o estado da conta.
        $matches = $this->passwordHasher->verify(
            $codeValue->value(),
            HashedPassword::fromHash($usable ? $challenge->codeHash() : DummyPasswordHash::VALUE),
        );

        if (!$matches) {
            if ($usable) {
                $this->challengeRepository->recordFailedAttempt($challenge, $this->maxAttempts);
            }

            return Result::failure([IdentityErrors::emailVerificationCodeInvalid()]);
        }

        if (!$usable) {
            return Result::failure([IdentityErrors::emailVerificationCodeInvalid()]);
        }

        $user = $challenge->user();

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
