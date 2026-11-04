<?php

declare(strict_types=1);

use App\Identity\Application\Exception\InvalidTokenException;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Identity\Infrastructure\Security\LcobucciAccessTokenGenerator;
use Symfony\Component\Clock\MockClock;

function accessTokenGenerator(MockClock $clock, string $secret = 'test-secret-with-at-least-32-characters!!'): LcobucciAccessTokenGenerator
{
    return new LcobucciAccessTokenGenerator(
        secret: $secret,
        issuer: 'sigame',
        audience: 'sigame-api',
        ttlSeconds: 900,
        clock: $clock,
    );
}

it('issues and parses an access token', function () {
    $clock = new MockClock('2026-01-01 00:00:00 UTC');
    $generator = accessTokenGenerator($clock);
    $userId = UserId::generate();

    $issued = $generator->issue($userId);
    $claims = $generator->parse($issued->token);

    expect($claims->userId->equals($userId))->toBeTrue()
        ->and($claims->expiresAt->timestamp())->toBe($issued->expiresAt->timestamp())
        ->and($issued->expiresAt->timestamp())->toBe($clock->now()->modify('+900 seconds')->getTimestamp());
});

it('rejects an expired access token', function () {
    $clock = new MockClock('2026-01-01 00:00:00 UTC');
    $generator = accessTokenGenerator($clock);

    $issued = $generator->issue(UserId::generate());

    $clock->modify('+16 minutes');

    expect(static fn () => $generator->parse($issued->token))
        ->toThrow(InvalidTokenException::class);
});

it('rejects a token signed with another secret', function () {
    $clock = new MockClock('2026-01-01 00:00:00 UTC');
    $issued = accessTokenGenerator($clock)->issue(UserId::generate());
    $other = accessTokenGenerator($clock, 'another-secret-with-at-least-32-chars!!!');

    expect(static fn () => $other->parse($issued->token))
        ->toThrow(InvalidTokenException::class);
});

it('rejects a token for another audience', function () {
    $clock = new MockClock('2026-01-01 00:00:00 UTC');
    $issued = accessTokenGenerator($clock)->issue(UserId::generate());

    $other = new LcobucciAccessTokenGenerator(
        secret: 'test-secret-with-at-least-32-characters!!',
        issuer: 'sigame',
        audience: 'another-api',
        ttlSeconds: 900,
        clock: $clock,
    );

    expect(static fn () => $other->parse($issued->token))
        ->toThrow(InvalidTokenException::class);
});
