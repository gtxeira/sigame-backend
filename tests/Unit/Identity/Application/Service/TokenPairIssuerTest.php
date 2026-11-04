<?php

declare(strict_types=1);

use App\Identity\Application\DTO\GeneratedRefreshToken;
use App\Identity\Application\DTO\IssuedToken;
use App\Identity\Application\Service\AccessTokenGeneratorInterface;
use App\Identity\Application\Service\RefreshTokenGeneratorInterface;
use App\Identity\Application\Service\TokenPairIssuer;
use App\Identity\Domain\RefreshToken\Entity\RefreshToken;
use App\Identity\Domain\RefreshToken\Repository\RefreshTokenRepositoryInterface;
use App\Identity\Domain\RefreshToken\ValueObject\TokenFamilyId;
use App\Identity\Domain\RefreshToken\ValueObject\TokenHash;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

function tokenPairUser(): User
{
    return User::register(
        cpf: null,
        email: Email::fromString('token-pair@example.com')->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Token Pair User',
    );
}

/**
 * @return array{
 *     issuer: TokenPairIssuer,
 *     access: Mockery\MockInterface,
 *     refresh: Mockery\MockInterface,
 *     repository: Mockery\MockInterface,
 *     lock: Mockery\MockInterface
 * }
 */
function tokenPairIssuer(
    MockClock $clock,
    int $graceSeconds = 30,
): array {
    $access = Mockery::mock(AccessTokenGeneratorInterface::class);
    $access->shouldReceive('issue')->andReturn(new IssuedToken('access-token', Instant::now()));

    $refresh = Mockery::mock(RefreshTokenGeneratorInterface::class);
    $repository = Mockery::mock(RefreshTokenRepositoryInterface::class);
    $lock = Mockery::mock(SharedLockInterface::class);
    $lock->shouldReceive('acquire')->andReturn(true)->byDefault();
    $lock->shouldReceive('release')->zeroOrMoreTimes()->byDefault();

    $lockFactory = Mockery::mock(LockFactory::class);
    $lockFactory->shouldReceive('createLock')->andReturn($lock);

    $issuer = new TokenPairIssuer(
        accessTokenGenerator: $access,
        refreshTokenGenerator: $refresh,
        refreshTokenRepository: $repository,
        lockFactory: $lockFactory,
        clock: $clock,
        refreshTtlSeconds: 604800,
        reuseGraceSeconds: $graceSeconds,
        maxFamilies: 5,
    );

    return compact('issuer', 'access', 'refresh', 'repository', 'lock');
}

it('issues a new session with access and opaque refresh tokens', function () {
    $clock = new MockClock('2026-01-01 00:00:00 UTC');
    $parts = tokenPairIssuer($clock);
    $user = tokenPairUser();
    $generated = new GeneratedRefreshToken('rt_'.str_repeat('a', 64), TokenHash::fromRaw('rt_'.str_repeat('a', 64)));

    $parts['refresh']->shouldReceive('generate')->once()->andReturn($generated);
    $parts['repository']->shouldReceive('save')->once()->with(Mockery::on(
        static fn (RefreshToken $token): bool => $token->user()->id()->equals($user->id())
            && $token->tokenHash()->equals($generated->hash),
    ));
    $parts['repository']->shouldReceive('keepNewestFamilies')->once()->with(Mockery::type(\App\Identity\Domain\User\ValueObject\UserId::class), 5);

    $pair = $parts['issuer']->issueForNewSession($user);

    expect($pair->accessToken->token)->toBe('access-token')
        ->and($pair->refreshToken)->toBe($generated->raw)
        ->and($pair->refreshExpiresAt->timestamp())->toBe($clock->now()->modify('+604800 seconds')->getTimestamp());
});

it('rotates an active refresh token', function () {
    $clock = new MockClock('2026-01-01 00:00:00 UTC');
    $parts = tokenPairIssuer($clock);
    $user = tokenPairUser();
    $family = TokenFamilyId::generate();
    $current = RefreshToken::create(
        user: $user,
        tokenHash: TokenHash::fromRaw('rt_old'),
        familyId: $family,
        expiresAt: Instant::fromDateTime($clock->now()->modify('+1 day')),
    );
    $newRaw = 'rt_'.str_repeat('b', 64);
    $generated = new GeneratedRefreshToken($newRaw, TokenHash::fromRaw($newRaw));

    $parts['repository']->shouldReceive('findByTokenHash')->once()->andReturn($current);
    $parts['refresh']->shouldReceive('hash')->once()->with('rt_old')->andReturn($current->tokenHash());
    $parts['refresh']->shouldReceive('generate')->once()->andReturn($generated);
    $parts['repository']->shouldReceive('rotate')->once()->with(
        $current,
        Mockery::on(static fn (RefreshToken $replacement): bool => $replacement->familyId()->equals($family)
            && $replacement->tokenHash()->equals($generated->hash)),
    );

    $result = $parts['issuer']->rotate('rt_old');

    expect($result->isSuccess())->toBeTrue();

    $pair = $result->value();

    expect($pair->refreshToken)->toBe($newRaw)
        ->and($current->isRevoked())->toBeTrue()
        ->and($current->replacedBy())->not->toBeNull();
});

it('rejects an unknown refresh token', function () {
    $clock = new MockClock('2026-01-01 00:00:00 UTC');
    $parts = tokenPairIssuer($clock);
    $parts['refresh']->shouldReceive('hash')->once()->andReturn(TokenHash::fromRaw('rt_unknown'));
    $parts['repository']->shouldReceive('findByTokenHash')->once()->andReturn(null);

    $result = $parts['issuer']->rotate('rt_unknown');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('REFRESH_TOKEN_INVALID');
});

it('rejects an expired refresh token', function () {
    $clock = new MockClock('2026-01-01 00:00:00 UTC');
    $parts = tokenPairIssuer($clock);
    $token = RefreshToken::create(
        user: tokenPairUser(),
        tokenHash: TokenHash::fromRaw('rt_expired'),
        familyId: TokenFamilyId::generate(),
        expiresAt: Instant::fromDateTime($clock->now()->modify('-1 second')),
    );

    $parts['refresh']->shouldReceive('hash')->once()->andReturn($token->tokenHash());
    $parts['repository']->shouldReceive('findByTokenHash')->once()->andReturn($token);
    $parts['repository']->shouldReceive('save')->once()->with($token);

    $result = $parts['issuer']->rotate('rt_expired');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('REFRESH_TOKEN_EXPIRED')
        ->and($token->isRevoked())->toBeTrue();
});

it('does not revoke the family when a just-rotated token is reused within the grace window', function () {
    $clock = new MockClock('2026-01-01 00:00:00 UTC');
    $parts = tokenPairIssuer($clock, graceSeconds: 30);
    $token = RefreshToken::create(
        user: tokenPairUser(),
        tokenHash: TokenHash::fromRaw('rt_rotated'),
        familyId: TokenFamilyId::generate(),
        expiresAt: Instant::fromDateTime($clock->now()->modify('+1 day')),
    );
    $token->revoke();

    $parts['refresh']->shouldReceive('hash')->once()->andReturn($token->tokenHash());
    $parts['repository']->shouldReceive('findByTokenHash')->once()->andReturn($token);
    $parts['repository']->shouldNotReceive('revokeFamily');

    $result = $parts['issuer']->rotate('rt_rotated');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('REFRESH_TOKEN_ALREADY_ROTATED');
});

it('revokes the family when a revoked token is reused outside the grace window', function () {
    $clock = new MockClock('2026-01-01 00:00:00 UTC');
    $parts = tokenPairIssuer($clock, graceSeconds: 0);
    $token = RefreshToken::create(
        user: tokenPairUser(),
        tokenHash: TokenHash::fromRaw('rt_reused'),
        familyId: TokenFamilyId::generate(),
        expiresAt: Instant::fromDateTime($clock->now()->modify('+1 day')),
    );
    $token->revoke();

    $parts['refresh']->shouldReceive('hash')->once()->andReturn($token->tokenHash());
    $parts['repository']->shouldReceive('findByTokenHash')->once()->andReturn($token);
    $parts['repository']->shouldReceive('revokeFamily')->once()->with($token->familyId());

    $result = $parts['issuer']->rotate('rt_reused');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('REFRESH_TOKEN_REUSED');
});

it('rejects a rotation when another request holds the lock', function () {
    $clock = new MockClock('2026-01-01 00:00:00 UTC');
    $access = Mockery::mock(AccessTokenGeneratorInterface::class);
    $refresh = Mockery::mock(RefreshTokenGeneratorInterface::class);
    $repository = Mockery::mock(RefreshTokenRepositoryInterface::class);
    $lock = Mockery::mock(SharedLockInterface::class);
    $lock->shouldReceive('acquire')->once()->andReturn(false);
    $lock->shouldReceive('release')->never();

    $lockFactory = Mockery::mock(LockFactory::class);
    $lockFactory->shouldReceive('createLock')->once()->andReturn($lock);

    $issuer = new TokenPairIssuer(
        accessTokenGenerator: $access,
        refreshTokenGenerator: $refresh,
        refreshTokenRepository: $repository,
        lockFactory: $lockFactory,
        clock: $clock,
    );

    $refresh->shouldReceive('hash')->once()->andReturn(TokenHash::fromRaw('rt_locked'));

    $result = $issuer->rotate('rt_locked');

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('REFRESH_IN_PROGRESS');
});
