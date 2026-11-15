<?php

declare(strict_types=1);

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Application\UseCase\Password\ResetPasswordHandler;
use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\PasswordReset\Entity\PasswordResetTicket;
use App\Identity\Domain\PasswordReset\Repository\PasswordResetTicketRepositoryInterface;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserEmailVerified;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\Clock\MockClock;

function resetPasswordTestUser(string $email = 'reset@example.com'): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('old-hash'),
        name: 'Reset User',
    );
}

function resetPasswordTestTicket(
    User $user,
    string $rawToken = 'raw-reset-ticket',
    ?Instant $expiresAt = null,
): PasswordResetTicket {
    $challenge = OtpChallenge::create(
        user: $user,
        purpose: OtpPurpose::PasswordReset,
        codeHash: 'code-hash',
        expiresAt: Instant::fromDateTime(new DateTimeImmutable('2026-01-01T00:10:00+00:00')),
    );

    return PasswordResetTicket::create(
        user: $user,
        challenge: $challenge,
        tokenHash: hash('sha256', $rawToken),
        expiresAt: $expiresAt ?? Instant::fromDateTime(
            new DateTimeImmutable('2026-01-01T00:05:00+00:00'),
        ),
    );
}

function resetPasswordTestHandler(
    PasswordResetTicketRepositoryInterface $tickets,
    UserRepositoryInterface $users,
    PasswordHasherInterface $hasher,
    TokenPairIssuerInterface $tokenPairIssuer,
    ?EventDispatcherInterface $eventDispatcher = null,
): ResetPasswordHandler {
    if (null === $eventDispatcher) {
        $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldReceive('dispatch');
    }

    return new ResetPasswordHandler(
        ticketRepository: $tickets,
        userRepository: $users,
        passwordHasher: $hasher,
        tokenPairIssuer: $tokenPairIssuer,
        eventDispatcher: $eventDispatcher,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
    );
}

it('resets the password and revokes every session', function () {
    $user = resetPasswordTestUser();
    $ticket = resetPasswordTestTicket($user);
    $newHash = HashedPassword::fromHash('new-hash');

    $tickets = Mockery::mock(PasswordResetTicketRepositoryInterface::class);
    $tickets->shouldReceive('findByHash')
        ->once()
        ->with(hash('sha256', 'raw-reset-ticket'))
        ->andReturn($ticket);
    $tickets->shouldReceive('markConsumed')->once()->with($ticket)->andReturn(true);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('save')->once()->with($user);

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->once()->with('new-secret-123')->andReturn($newHash);

    $tokenPairIssuer = Mockery::mock(TokenPairIssuerInterface::class);
    $tokenPairIssuer->shouldReceive('revokeAllSessions')->once()->with($user->id());

    $result = resetPasswordTestHandler($tickets, $users, $hasher, $tokenPairIssuer)(
        'raw-reset-ticket',
        'new-secret-123',
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($user->password()?->value())->toBe('new-hash')
        ->and($user->isEmailVerified())->toBeTrue();
});

it('verifies an unverified email after a successful reset', function () {
    $user = resetPasswordTestUser('unverified-reset@example.com');
    $ticket = resetPasswordTestTicket($user);

    $tickets = Mockery::mock(PasswordResetTicketRepositoryInterface::class);
    $tickets->shouldReceive('findByHash')->once()->andReturn($ticket);
    $tickets->shouldReceive('markConsumed')->once()->andReturn(true);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('save')->once()->with($user);

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->once()->andReturn(HashedPassword::fromHash('new-hash'));

    $tokenPairIssuer = Mockery::mock(TokenPairIssuerInterface::class);
    $tokenPairIssuer->shouldReceive('revokeAllSessions')->once();

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldReceive('dispatch')
        ->once()
        ->with(Mockery::on(
            static fn (UserEmailVerified $event): bool => $user->id()->value() === $event->userId()
                && 'unverified-reset@example.com' === $event->email(),
        ));

    expect($user->isEmailVerified())->toBeFalse();

    $result = resetPasswordTestHandler($tickets, $users, $hasher, $tokenPairIssuer, $events)(
        'raw-reset-ticket',
        'new-secret-123',
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($user->isEmailVerified())->toBeTrue()
        ->and($user->emailVerifiedAt()?->format())->toBe('2026-01-01T00:00:00.000+00:00');
});

it('creates a local credential for a social-only account', function () {
    $user = User::registerWithProvider(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google:social-reset@example.com'),
        email: Email::fromString('social-reset@example.com')->value(),
        name: 'Social Reset',
    );
    $ticket = resetPasswordTestTicket($user);

    $tickets = Mockery::mock(PasswordResetTicketRepositoryInterface::class);
    $tickets->shouldReceive('findByHash')->once()->andReturn($ticket);
    $tickets->shouldReceive('markConsumed')->once()->andReturn(true);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('save')->once();

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->once()->andReturn(HashedPassword::fromHash('created-hash'));

    $tokenPairIssuer = Mockery::mock(TokenPairIssuerInterface::class);
    $tokenPairIssuer->shouldReceive('revokeAllSessions')->once();

    $events = Mockery::mock(EventDispatcherInterface::class);
    $events->shouldNotReceive('dispatch');

    expect($user->hasLocalPassword())->toBeFalse();

    $result = resetPasswordTestHandler($tickets, $users, $hasher, $tokenPairIssuer, $events)(
        'raw-reset-ticket',
        'new-secret-123',
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($user->hasLocalPassword())->toBeTrue()
        ->and($user->identities())->toHaveCount(2)
        ->and($user->isEmailVerified())->toBeTrue();
});

it('rejects an unknown ticket', function () {
    $tickets = Mockery::mock(PasswordResetTicketRepositoryInterface::class);
    $tickets->shouldReceive('findByHash')->once()->andReturn(null);
    $tickets->shouldNotReceive('markConsumed');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('save');

    $result = resetPasswordTestHandler(
        $tickets,
        $users,
        Mockery::mock(PasswordHasherInterface::class),
        Mockery::mock(TokenPairIssuerInterface::class),
    )('missing-ticket', 'new-secret-123');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('PASSWORD_RESET_TICKET_INVALID');
});

it('rejects an expired ticket', function () {
    $user = resetPasswordTestUser('expired@example.com');
    $ticket = resetPasswordTestTicket(
        $user,
        expiresAt: Instant::fromDateTime(new DateTimeImmutable('2025-12-31T23:59:59+00:00')),
    );

    $tickets = Mockery::mock(PasswordResetTicketRepositoryInterface::class);
    $tickets->shouldReceive('findByHash')->once()->andReturn($ticket);
    $tickets->shouldNotReceive('markConsumed');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('save');

    $result = resetPasswordTestHandler(
        $tickets,
        $users,
        Mockery::mock(PasswordHasherInterface::class),
        Mockery::mock(TokenPairIssuerInterface::class),
    )('raw-reset-ticket', 'new-secret-123');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('PASSWORD_RESET_TICKET_EXPIRED');
});

it('rejects the ticket when the atomic consumption loses a race', function () {
    $user = resetPasswordTestUser('race@example.com');
    $ticket = resetPasswordTestTicket($user);

    $tickets = Mockery::mock(PasswordResetTicketRepositoryInterface::class);
    $tickets->shouldReceive('findByHash')->once()->andReturn($ticket);
    $tickets->shouldReceive('markConsumed')->once()->andReturn(false);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('save');

    $tokenPairIssuer = Mockery::mock(TokenPairIssuerInterface::class);
    $tokenPairIssuer->shouldNotReceive('revokeAllSessions');

    $result = resetPasswordTestHandler(
        $tickets,
        $users,
        Mockery::mock(PasswordHasherInterface::class),
        $tokenPairIssuer,
    )('raw-reset-ticket', 'new-secret-123');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('PASSWORD_RESET_TICKET_INVALID');
});

it('rejects a reset for a suspended account', function () {
    $user = resetPasswordTestUser('suspended@example.com');
    $user->suspend();
    $ticket = resetPasswordTestTicket($user);

    $tickets = Mockery::mock(PasswordResetTicketRepositoryInterface::class);
    $tickets->shouldReceive('findByHash')->once()->andReturn($ticket);
    $tickets->shouldReceive('markConsumed')->once()->andReturn(true);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('save');

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldNotReceive('hash');

    $tokenPairIssuer = Mockery::mock(TokenPairIssuerInterface::class);
    $tokenPairIssuer->shouldNotReceive('revokeAllSessions');

    $result = resetPasswordTestHandler($tickets, $users, $hasher, $tokenPairIssuer)(
        'raw-reset-ticket',
        'new-secret-123',
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('ACCOUNT_SUSPENDED');
});

it('rejects a password that does not meet the policy before looking up the ticket', function () {
    $tickets = Mockery::mock(PasswordResetTicketRepositoryInterface::class);
    $tickets->shouldNotReceive('findByHash');

    $result = resetPasswordTestHandler(
        $tickets,
        Mockery::mock(UserRepositoryInterface::class),
        Mockery::mock(PasswordHasherInterface::class),
        Mockery::mock(TokenPairIssuerInterface::class),
    )('raw-reset-ticket', 'short');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('STRING_TOO_SHORT')
        ->and($result->errors()[0]->field)->toBe('password');
});
