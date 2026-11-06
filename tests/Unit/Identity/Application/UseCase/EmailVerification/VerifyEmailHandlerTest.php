<?php

declare(strict_types=1);

use App\Identity\Application\UseCase\EmailVerification\VerifyEmailHandler;
use App\Identity\Domain\IdentityToken\Entity\IdentityToken;
use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserEmailVerified;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\Clock\MockClock;

function verifyEmailTestUser(string $email = 'verify-me@example.com'): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Verify Me',
    );
}

function verifyEmailTestToken(
    User $user,
    string $rawToken = 'raw-verification-token',
    ?Instant $expiresAt = null,
    IdentityTokenPurpose $purpose = IdentityTokenPurpose::EmailVerification,
): IdentityToken {
    return IdentityToken::create(
        user: $user,
        purpose: $purpose,
        tokenHash: hash('sha256', $rawToken),
        expiresAt: $expiresAt ?? Instant::fromDateTime(
            new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
        ),
    );
}

it('verifies the email and dispatches the domain event', function () {
    $user = verifyEmailTestUser();
    $token = verifyEmailTestToken($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->with(hash('sha256', 'raw-verification-token'))->andReturn($token);
    $tokens->shouldReceive('markConsumed')->once()->with($token)->andReturn(true);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('save')->once()->with($user);

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(
            static fn (UserEmailVerified $event): bool => $user->id()->value() === $event->userId()
                && 'verify-me@example.com' === $event->email(),
        ));

    $handler = new VerifyEmailHandler(
        tokenRepository: $tokens,
        userRepository: $users,
        eventDispatcher: $events,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
    );

    $result = $handler('raw-verification-token');

    expect($result->isSuccess())->toBeTrue()
        ->and($user->isEmailVerified())->toBeTrue()
        ->and($token->isConsumed())->toBeFalse();
});

it('rejects an unknown token', function () {
    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->andReturn(null);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('save');

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldNotReceive('dispatch');

    $handler = new VerifyEmailHandler(
        tokenRepository: $tokens,
        userRepository: $users,
        eventDispatcher: $events,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
    );

    $result = $handler('missing-token');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('EMAIL_VERIFICATION_TOKEN_INVALID');
});

it('rejects a token issued for another purpose', function () {
    $user = verifyEmailTestUser();
    $token = verifyEmailTestToken($user, purpose: IdentityTokenPurpose::PasswordReset);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->andReturn($token);
    $tokens->shouldNotReceive('markConsumed');

    $handler = new VerifyEmailHandler(
        tokenRepository: $tokens,
        userRepository: Mockery::mock(UserRepositoryInterface::class),
        eventDispatcher: Mockery::mock(EventDispatcherInterface::class),
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
    );

    $result = $handler('raw-verification-token');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('EMAIL_VERIFICATION_TOKEN_INVALID');
});

it('rejects an already consumed token', function () {
    $user = verifyEmailTestUser();
    $token = verifyEmailTestToken($user);
    $token->consume(Instant::fromDateTime(new DateTimeImmutable('2025-12-31T00:00:00+00:00')));

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->andReturn($token);
    $tokens->shouldNotReceive('markConsumed');

    $handler = new VerifyEmailHandler(
        tokenRepository: $tokens,
        userRepository: Mockery::mock(UserRepositoryInterface::class),
        eventDispatcher: Mockery::mock(EventDispatcherInterface::class),
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
    );

    $result = $handler('raw-verification-token');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('EMAIL_VERIFICATION_TOKEN_INVALID');
});

it('rejects an expired token', function () {
    $user = verifyEmailTestUser();
    $token = verifyEmailTestToken(
        $user,
        expiresAt: Instant::fromDateTime(new DateTimeImmutable('2025-12-31T23:59:59+00:00')),
    );

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->andReturn($token);
    $tokens->shouldNotReceive('markConsumed');

    $handler = new VerifyEmailHandler(
        tokenRepository: $tokens,
        userRepository: Mockery::mock(UserRepositoryInterface::class),
        eventDispatcher: Mockery::mock(EventDispatcherInterface::class),
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
    );

    $result = $handler('raw-verification-token');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('EMAIL_VERIFICATION_TOKEN_EXPIRED');
});

it('rejects the token when the atomic consumption loses a race', function () {
    $user = verifyEmailTestUser();
    $token = verifyEmailTestToken($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->andReturn($token);
    $tokens->shouldReceive('markConsumed')->once()->andReturn(false);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('save');

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldNotReceive('dispatch');

    $handler = new VerifyEmailHandler(
        tokenRepository: $tokens,
        userRepository: $users,
        eventDispatcher: $events,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
    );

    $result = $handler('raw-verification-token');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('EMAIL_VERIFICATION_TOKEN_INVALID')
        ->and($user->isEmailVerified())->toBeFalse();
});
