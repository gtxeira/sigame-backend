<?php

declare(strict_types=1);

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\UseCase\Password\VerifyPasswordResetOtpHandler;
use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\Otp\Repository\OtpChallengeRepositoryInterface;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\PasswordReset\Entity\PasswordResetTicket;
use App\Identity\Domain\PasswordReset\Repository\PasswordResetTicketRepositoryInterface;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\Clock\MockClock;

function verifyResetOtpTestUser(string $email = 'reset-otp@example.com'): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Reset OTP',
    );
}

function verifyResetOtpTestChallenge(User $user, ?Instant $expiresAt = null): OtpChallenge
{
    return OtpChallenge::create(
        user: $user,
        purpose: OtpPurpose::PasswordReset,
        codeHash: 'code-hash',
        expiresAt: $expiresAt ?? Instant::fromDateTime(
            new DateTimeImmutable('2026-01-01T00:10:00+00:00'),
        ),
    );
}

function verifyResetOtpTestHandler(
    OtpChallengeRepositoryInterface $challenges,
    PasswordResetTicketRepositoryInterface $tickets,
    UserRepositoryInterface $users,
    PasswordHasherInterface $hasher,
): VerifyPasswordResetOtpHandler {
    return new VerifyPasswordResetOtpHandler(
        challengeRepository: $challenges,
        ticketRepository: $tickets,
        userRepository: $users,
        passwordHasher: $hasher,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        maxAttempts: 5,
        ticketTtlSeconds: 300,
    );
}

it('validates the code and issues a five-minute single-use ticket', function () {
    $user = verifyResetOtpTestUser();
    $challenge = verifyResetOtpTestChallenge($user);
    $saved = null;

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('findActive')->once()->andReturn($challenge);
    $challenges->shouldReceive('markConsumed')->once()->with($challenge)->andReturn(true);

    $tickets = Mockery::mock(PasswordResetTicketRepositoryInterface::class);
    $tickets->shouldReceive('invalidateActive')->once()->with($user);
    $tickets->shouldReceive('save')->once()->with(Mockery::on(
        static function (PasswordResetTicket $ticket) use (&$saved): bool {
            $saved = $ticket;

            return true;
        },
    ));

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')
        ->once()
        ->with('123456', Mockery::on(static fn (HashedPassword $hash): bool => 'code-hash' === $hash->value()))
        ->andReturn(true);

    $result = verifyResetOtpTestHandler($challenges, $tickets, $users, $hasher)(
        'reset-otp@example.com',
        '123456',
    );

    expect($result->isSuccess())->toBeTrue();

    $issued = $result->value();

    expect($issued->expiresIn)->toBe(300)
        ->and($issued->rawToken)->toMatch('/^[a-f0-9]{64}$/')
        ->and($saved)->toBeInstanceOf(PasswordResetTicket::class)
        ->and($saved?->user())->toBe($user)
        ->and($saved?->challenge())->toBe($challenge)
        ->and($saved?->tokenHash())->toBe(hash('sha256', $issued->rawToken))
        ->and($saved?->expiresAt()->equals(
            Instant::fromDateTime(new DateTimeImmutable('2026-01-01T00:05:00+00:00')),
        ))->toBeTrue();
});

it('counts a wrong code without issuing a ticket', function () {
    $user = verifyResetOtpTestUser();
    $challenge = verifyResetOtpTestChallenge($user);

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('findActive')->once()->andReturn($challenge);
    $challenges->shouldReceive('recordFailedAttempt')->once()->andReturn(1);
    $challenges->shouldNotReceive('markConsumed');

    $tickets = Mockery::mock(PasswordResetTicketRepositoryInterface::class);
    $tickets->shouldNotReceive('save');
    $tickets->shouldNotReceive('invalidateActive');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('verify')->once()->andReturn(false);

    $result = verifyResetOtpTestHandler($challenges, $tickets, $users, $hasher)(
        'reset-otp@example.com',
        '000000',
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('PASSWORD_RESET_CODE_INVALID');
});

it('rejects a suspended account before validating the code', function () {
    $user = verifyResetOtpTestUser();
    $user->suspend();

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldNotReceive('findActive');

    $tickets = Mockery::mock(PasswordResetTicketRepositoryInterface::class);
    $tickets->shouldNotReceive('save');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $result = verifyResetOtpTestHandler(
        $challenges,
        $tickets,
        $users,
        Mockery::mock(PasswordHasherInterface::class),
    )('reset-otp@example.com', '123456');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('ACCOUNT_SUSPENDED');
});

it('rejects an expired reset code', function () {
    $user = verifyResetOtpTestUser();
    $challenge = verifyResetOtpTestChallenge(
        $user,
        Instant::fromDateTime(new DateTimeImmutable('2025-12-31T23:59:00+00:00')),
    );

    $challenges = Mockery::mock(OtpChallengeRepositoryInterface::class);
    $challenges->shouldReceive('findActive')->once()->andReturn($challenge);
    $challenges->shouldNotReceive('recordFailedAttempt');

    $tickets = Mockery::mock(PasswordResetTicketRepositoryInterface::class);
    $tickets->shouldNotReceive('save');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('findByEmail')->once()->andReturn($user);

    $result = verifyResetOtpTestHandler(
        $challenges,
        $tickets,
        $users,
        Mockery::mock(PasswordHasherInterface::class),
    )('reset-otp@example.com', '123456');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('PASSWORD_RESET_CODE_EXPIRED');
});
