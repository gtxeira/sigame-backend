<?php

declare(strict_types=1);

use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\EntityManagerInterface;
use Tests\IntegrationTestCase;

function otpRepositoryUser(IntegrationTestCase $test, string $email): User
{
    $user = User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'OTP Repository User',
    );

    $test->service(UserRepositoryInterface::class)->save($user);

    return $user;
}

function otpRepositoryChallenge(
    User $user,
    OtpPurpose $purpose = OtpPurpose::EmailVerification,
    string $codeHash = 'code-hash',
): OtpChallenge {
    return OtpChallenge::create(
        user: $user,
        purpose: $purpose,
        codeHash: $codeHash,
        expiresAt: Instant::fromDateTime((new DateTimeImmutable())->modify('+10 minutes')),
    );
}

it('persists only the code hash and finds the active challenge', function () {
    $repository = $this->service(OtpChallengeRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = otpRepositoryUser($this, 'otp-repo@example.com');
    $challenge = otpRepositoryChallenge($user);

    $repository->save($challenge);
    $em->clear();

    $found = $repository->findActive($user, OtpPurpose::EmailVerification);

    assert($found instanceof OtpChallenge);

    expect($found->id()->equals($challenge->id()))->toBeTrue()
        ->and($found->purpose())->toBe(OtpPurpose::EmailVerification)
        ->and($found->attempts())->toBe(0);

    $stored = $em->getConnection()->fetchOne(
        'SELECT code_hash FROM identity_otp_challenges WHERE id = ?',
        [$challenge->id()->value()],
    );

    expect($stored)->toBe('code-hash');
});

it('consumes the challenge atomically only once', function () {
    $repository = $this->service(OtpChallengeRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = otpRepositoryUser($this, 'otp-consume@example.com');
    $challenge = otpRepositoryChallenge($user);

    $repository->save($challenge);

    expect($repository->markConsumed($challenge))->toBeTrue()
        ->and($repository->markConsumed($challenge))->toBeFalse();

    $em->clear();

    expect($repository->findActive($user, OtpPurpose::EmailVerification))->toBeNull();
});

it('increments attempts atomically and invalidates at the limit', function () {
    $repository = $this->service(OtpChallengeRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = otpRepositoryUser($this, 'otp-attempts@example.com');
    $challenge = otpRepositoryChallenge($user);

    $repository->save($challenge);

    expect($repository->recordFailedAttempt($challenge, 5))->toBe(1)
        ->and($repository->recordFailedAttempt($challenge, 5))->toBe(2)
        ->and($repository->recordFailedAttempt($challenge, 5))->toBe(3)
        ->and($repository->recordFailedAttempt($challenge, 5))->toBe(4)
        ->and($repository->recordFailedAttempt($challenge, 5))->toBe(5);

    $em->clear();

    expect($repository->findActive($user, OtpPurpose::EmailVerification))->toBeNull();
});

it('invalidates the active challenge of a purpose only', function () {
    $repository = $this->service(OtpChallengeRepositoryInterface::class);
    $em = $this->service(EntityManagerInterface::class);
    $user = otpRepositoryUser($this, 'otp-invalidate@example.com');
    $emailChallenge = otpRepositoryChallenge($user);
    $resetChallenge = otpRepositoryChallenge($user, OtpPurpose::PasswordReset);

    $repository->save($emailChallenge);
    $repository->save($resetChallenge);
    $repository->invalidateActive($user, OtpPurpose::EmailVerification);
    $em->clear();

    expect($repository->findActive($user, OtpPurpose::EmailVerification))->toBeNull()
        ->and($repository->findActive($user, OtpPurpose::PasswordReset))->not->toBeNull();
});

it('reports whether an active challenge was created after a given instant', function () {
    $repository = $this->service(OtpChallengeRepositoryInterface::class);
    $user = otpRepositoryUser($this, 'otp-created@example.com');
    $challenge = otpRepositoryChallenge($user);

    $repository->save($challenge);

    expect($repository->hasActiveCreatedAfter(
        $user,
        OtpPurpose::EmailVerification,
        Instant::fromDateTime(new DateTimeImmutable('-1 minute')),
    ))->toBeTrue()
        ->and($repository->hasActiveCreatedAfter(
            $user,
            OtpPurpose::EmailVerification,
            Instant::fromDateTime(new DateTimeImmutable('+1 minute')),
        ))->toBeFalse();
});
