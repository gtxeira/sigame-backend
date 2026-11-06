<?php

declare(strict_types=1);

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Application\UseCase\Password\ResetPasswordHandler;
use App\Identity\Domain\IdentityToken\Entity\IdentityToken;
use App\Identity\Domain\IdentityToken\Repository\IdentityTokenRepositoryInterface;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\ProviderSubject;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\Clock\MockClock;

function resetPasswordTestToken(
    User $user,
    string $rawToken = 'raw-reset-token',
    ?Instant $expiresAt = null,
    IdentityTokenPurpose $purpose = IdentityTokenPurpose::PasswordReset,
): IdentityToken {
    return IdentityToken::create(
        user: $user,
        purpose: $purpose,
        tokenHash: hash('sha256', $rawToken),
        expiresAt: $expiresAt ?? Instant::fromDateTime(
            new DateTimeImmutable('2026-01-01T00:30:00+00:00'),
        ),
    );
}

function resetPasswordTestHandler(
    IdentityTokenRepositoryInterface $tokens,
    UserRepositoryInterface $users,
    PasswordHasherInterface $hasher,
    TokenPairIssuerInterface $tokenPairIssuer,
): ResetPasswordHandler {
    return new ResetPasswordHandler(
        tokenRepository: $tokens,
        userRepository: $users,
        passwordHasher: $hasher,
        tokenPairIssuer: $tokenPairIssuer,
        clock: new MockClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
    );
}

it('resets the password and revokes every session', function () {
    $user = User::register(
        cpf: null,
        email: Email::fromString('reset@example.com')->value(),
        password: HashedPassword::fromHash('old-hash'),
        name: 'Reset User',
    );
    $token = resetPasswordTestToken($user);
    $newHash = HashedPassword::fromHash('new-hash');

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->andReturn($token);
    $tokens->shouldReceive('markConsumed')->once()->with($token)->andReturn(true);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('save')->once()->with($user);

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->once()->with('new-secret-123')->andReturn($newHash);

    $tokenPairIssuer = Mockery::mock(TokenPairIssuerInterface::class);
    $tokenPairIssuer->shouldReceive('revokeAllSessions')->once()->with($user->id());

    $result = resetPasswordTestHandler($tokens, $users, $hasher, $tokenPairIssuer)(
        'raw-reset-token',
        'new-secret-123',
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($user->password()?->value())->toBe('new-hash');
});

it('creates a local credential for a social-only account', function () {
    $user = User::registerWithProvider(
        provider: IdentityProvider::GOOGLE,
        subject: ProviderSubject::fromString('google:social-reset@example.com'),
        email: Email::fromString('social-reset@example.com')->value(),
        name: 'Social Reset',
    );
    $token = resetPasswordTestToken($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->andReturn($token);
    $tokens->shouldReceive('markConsumed')->once()->andReturn(true);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('save')->once();

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldReceive('hash')->once()->andReturn(HashedPassword::fromHash('created-hash'));

    $tokenPairIssuer = Mockery::mock(TokenPairIssuerInterface::class);
    $tokenPairIssuer->shouldReceive('revokeAllSessions')->once();

    expect($user->hasLocalPassword())->toBeFalse();

    $result = resetPasswordTestHandler($tokens, $users, $hasher, $tokenPairIssuer)(
        'raw-reset-token',
        'new-secret-123',
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($user->hasLocalPassword())->toBeTrue()
        ->and($user->identities())->toHaveCount(2);
});

it('rejects an unknown token', function () {
    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->andReturn(null);
    $tokens->shouldNotReceive('markConsumed');

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('save');

    $result = resetPasswordTestHandler(
        $tokens,
        $users,
        Mockery::mock(PasswordHasherInterface::class),
        Mockery::mock(TokenPairIssuerInterface::class),
    )('missing-token', 'new-secret-123');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('PASSWORD_RESET_TOKEN_INVALID');
});

it('rejects a token issued for email verification', function () {
    $user = User::register(
        cpf: null,
        email: Email::fromString('wrong-purpose@example.com')->value(),
        password: HashedPassword::fromHash('old-hash'),
        name: 'Wrong Purpose',
    );
    $token = resetPasswordTestToken($user, purpose: IdentityTokenPurpose::EmailVerification);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->andReturn($token);
    $tokens->shouldNotReceive('markConsumed');

    $result = resetPasswordTestHandler(
        $tokens,
        Mockery::mock(UserRepositoryInterface::class),
        Mockery::mock(PasswordHasherInterface::class),
        Mockery::mock(TokenPairIssuerInterface::class),
    )('raw-reset-token', 'new-secret-123');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('PASSWORD_RESET_TOKEN_INVALID');
});

it('rejects an expired token', function () {
    $user = User::register(
        cpf: null,
        email: Email::fromString('expired@example.com')->value(),
        password: HashedPassword::fromHash('old-hash'),
        name: 'Expired',
    );
    $token = resetPasswordTestToken(
        $user,
        expiresAt: Instant::fromDateTime(new DateTimeImmutable('2025-12-31T23:59:59+00:00')),
    );

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->andReturn($token);
    $tokens->shouldNotReceive('markConsumed');

    $result = resetPasswordTestHandler(
        $tokens,
        Mockery::mock(UserRepositoryInterface::class),
        Mockery::mock(PasswordHasherInterface::class),
        Mockery::mock(TokenPairIssuerInterface::class),
    )('raw-reset-token', 'new-secret-123');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('PASSWORD_RESET_TOKEN_EXPIRED');
});

it('rejects the token when the atomic consumption loses a race', function () {
    $user = User::register(
        cpf: null,
        email: Email::fromString('race@example.com')->value(),
        password: HashedPassword::fromHash('old-hash'),
        name: 'Race',
    );
    $token = resetPasswordTestToken($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->andReturn($token);
    $tokens->shouldReceive('markConsumed')->once()->andReturn(false);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('save');

    $tokenPairIssuer = Mockery::mock(TokenPairIssuerInterface::class);
    $tokenPairIssuer->shouldNotReceive('revokeAllSessions');

    $result = resetPasswordTestHandler(
        $tokens,
        $users,
        Mockery::mock(PasswordHasherInterface::class),
        $tokenPairIssuer,
    )('raw-reset-token', 'new-secret-123');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('PASSWORD_RESET_TOKEN_INVALID');
});

it('rejects a reset for a suspended account', function () {
    $user = User::register(
        cpf: null,
        email: Email::fromString('suspended@example.com')->value(),
        password: HashedPassword::fromHash('old-hash'),
        name: 'Suspended',
    );
    $user->suspend('fraud');
    $token = resetPasswordTestToken($user);

    $tokens = Mockery::mock(IdentityTokenRepositoryInterface::class);
    $tokens->shouldReceive('findByHash')->once()->andReturn($token);
    $tokens->shouldReceive('markConsumed')->once()->andReturn(true);

    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldNotReceive('save');

    $hasher = Mockery::mock(PasswordHasherInterface::class);
    $hasher->shouldNotReceive('hash');

    $tokenPairIssuer = Mockery::mock(TokenPairIssuerInterface::class);
    $tokenPairIssuer->shouldNotReceive('revokeAllSessions');

    $result = resetPasswordTestHandler($tokens, $users, $hasher, $tokenPairIssuer)(
        'raw-reset-token',
        'new-secret-123',
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('ACCOUNT_SUSPENDED');
});
