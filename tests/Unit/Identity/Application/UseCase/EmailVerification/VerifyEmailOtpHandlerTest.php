<?php

declare(strict_types=1);

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\UseCase\EmailVerification\VerifyEmailOtpHandler;
use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserEmailVerified;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\Clock\MockClock;

function verifyEmailOtpTestUser(string $email = 'verify-otp@example.com'): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Verify OTP',
    );
}

function verifyEmailOtpTestChallenge(
    User $user,
    ?Instant $expiresAt = null,
    int $attempts = 0,
): OtpChallenge {
    $challenge = OtpChallenge::create(
        user: $user,
        purpose: OtpPurpose::EmailVerification,
        codeHash: 'code-hash',
        expiresAt: $expiresAt ?? Instant::fromDateTime(
            new DateTimeImmutable('2026-01-01T00:15:00+00:00'),
        ),
    );

    for ($attempt = 0; $attempt < $attempts; ++$attempt) {
        $challenge->recordFailedAttempt(5, Instant::fromDateTime(
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        ));
    }

    return $challenge;
}

function verifyEmailOtpTestHandler(
    OtpChallengeRepositoryInterface $challenges,
    UserRepositoryInterface $users,
    PasswordHasherInterface $hasher,
    ?EventDispatcherInterface $events = null,
): VerifyEmailOtpHandler {
    if (null === $events) {
        $events = Mockery::mock(EventDispatcherInterface::class);
        $events->shouldReceive('dispatch');
    }

    return new VerifyEmailOtpHandler(
        challengeRepository: $challenges,
        userRepository: $users,
        passwordHasher: $hasher,
        eventDispatcher: $events,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        maxAttempts: 5,
    );
}

it('verifies the email and consumes the code', function () {
    $user = verifyEmailOtpTestUser();
    $challenge = verifyEmailOtpTestChallenge($user);

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('findActive')->once()->andReturn($challenge);
    $challenges->shouldReceive('markConsumed')->once()->with($challenge)->andReturn(true);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);
    $users->shouldReceive('save')->once()->with($user);

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')
        ->once()
        ->with('123456', Mockery::on(static fn (HashedPassword $hash): bool => 'code-hash' === $hash->value()))
        ->andReturn(true);

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(static fn (UserEmailVerified $event): bool => $user->id()->value() === $event->userId()));

    $result = verifyEmailOtpTestHandler($challenges, $users, $hasher, $events)(
        'verify-otp@example.com',
        '123456',
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($user->isEmailVerified())->toBeTrue();
});

it('counts a wrong code and keeps the challenge active', function () {
    $user = verifyEmailOtpTestUser();
    $challenge = verifyEmailOtpTestChallenge($user);

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('findActive')->once()->andReturn($challenge);
    $challenges->shouldReceive('recordFailedAttempt')->once()->andReturn(1);
    $challenges->shouldNotReceive('markConsumed');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);
    $users->shouldNotReceive('save');

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->once()->andReturn(false);

    $result = verifyEmailOtpTestHandler($challenges, $users, $hasher)(
        'verify-otp@example.com',
        '000000',
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('EMAIL_VERIFICATION_CODE_INVALID');
});

it('invalidates the challenge on the fifth wrong attempt', function () {
    $user = verifyEmailOtpTestUser();
    $challenge = verifyEmailOtpTestChallenge($user);

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('findActive')->once()->andReturn($challenge);
    $challenges->shouldReceive('recordFailedAttempt')->once()->andReturn(5);
    $challenges->shouldNotReceive('markConsumed');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->once()->andReturn(false);

    $result = verifyEmailOtpTestHandler($challenges, $users, $hasher)(
        'verify-otp@example.com',
        '000000',
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('EMAIL_VERIFICATION_CODE_ATTEMPTS_EXCEEDED');
});

it('rejects an expired code', function () {
    $user = verifyEmailOtpTestUser();
    $challenge = verifyEmailOtpTestChallenge(
        $user,
        Instant::fromDateTime(new DateTimeImmutable('2025-12-31T23:59:00+00:00')),
    );

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('findActive')->once()->andReturn($challenge);
    $challenges->shouldNotReceive('recordFailedAttempt');
    $challenges->shouldNotReceive('markConsumed');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $result = verifyEmailOtpTestHandler(
        $challenges,
        $users,
        Mockery::mock(PasswordHasherInterface::class),
    )('verify-otp@example.com', '123456');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('EMAIL_VERIFICATION_CODE_EXPIRED');
});

it('rejects an unknown email without revealing the account', function () {
    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldNotReceive('findActive');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn(null);

    $result = verifyEmailOtpTestHandler(
        $challenges,
        $users,
        Mockery::mock(PasswordHasherInterface::class),
    )('unknown@example.com', '123456');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('EMAIL_VERIFICATION_CODE_INVALID');
});

it('rejects a malformed code before touching the repository', function () {
    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldNotReceive('findActive');

    $result = verifyEmailOtpTestHandler(
        $challenges,
        Mockery::mock(UserRepositoryInterface::class),
        Mockery::mock(PasswordHasherInterface::class),
    )('verify-otp@example.com', '12');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('INVALID_FORMAT');
});
