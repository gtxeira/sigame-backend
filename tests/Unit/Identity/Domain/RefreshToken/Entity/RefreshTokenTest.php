<?php

declare(strict_types=1);

use App\Identity\Domain\RefreshToken\Entity\RefreshToken;
use App\Identity\Domain\RefreshToken\ValueObject\TokenFamilyId;
use App\Identity\Domain\RefreshToken\ValueObject\TokenHash;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;

function refreshTokenUser(): User
{
    return User::register(
        cpf: null,
        email: Email::fromString('refresh-token@example.com')->value(),
        password: HashedPassword::fromHash('hashed'),
        name: 'Refresh User',
    );
}

it('creates a refresh token in a family', function () {
    $family = TokenFamilyId::generate();
    $expiresAt = Instant::fromDateTime((new DateTimeImmutable())->modify('+1 hour'));

    $token = RefreshToken::create(
        user: refreshTokenUser(),
        tokenHash: TokenHash::fromRaw('rt_raw'),
        familyId: $family,
        expiresAt: $expiresAt,
    );

    expect($token->familyId()->equals($family))->toBeTrue()
        ->and($token->belongsToFamily($family))->toBeTrue()
        ->and($token->isRevoked())->toBeFalse()
        ->and($token->replacedBy())->toBeNull();
});

it('detects expiration', function () {
    $now = Instant::fromDateTime(new DateTimeImmutable());

    $token = RefreshToken::create(
        user: refreshTokenUser(),
        tokenHash: TokenHash::fromRaw('rt_raw'),
        familyId: TokenFamilyId::generate(),
        expiresAt: Instant::fromDateTime((new DateTimeImmutable())->modify('-1 second')),
    );

    expect($token->isExpiredAt($now))->toBeTrue();
});

it('revokes and links the replacement token', function () {
    $family = TokenFamilyId::generate();
    $expiresAt = Instant::fromDateTime((new DateTimeImmutable())->modify('+1 hour'));

    $first = RefreshToken::create(
        user: refreshTokenUser(),
        tokenHash: TokenHash::fromRaw('rt_first'),
        familyId: $family,
        expiresAt: $expiresAt,
    );

    $second = RefreshToken::create(
        user: $first->user(),
        tokenHash: TokenHash::fromRaw('rt_second'),
        familyId: $family,
        expiresAt: $expiresAt,
    );

    $first->revoke($second);

    expect($first->isRevoked())->toBeTrue()
        ->and($first->replacedBy())->toBe($second)
        ->and($first->wasRevokedWithin(Instant::now(), 30))->toBeTrue()
        ->and($first->wasRevokedWithin(Instant::now(), 0))->toBeFalse();
});
