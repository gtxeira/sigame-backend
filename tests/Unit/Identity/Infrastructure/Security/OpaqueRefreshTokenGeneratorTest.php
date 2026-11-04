<?php

declare(strict_types=1);

use App\Identity\Domain\RefreshToken\ValueObject\TokenHash;
use App\Identity\Infrastructure\Security\OpaqueRefreshTokenGenerator;

it('generates an opaque high entropy refresh token', function () {
    $generator = new OpaqueRefreshTokenGenerator();

    $generated = $generator->generate();

    expect($generated->raw)->toMatch('/^rt_[a-f0-9]{64}$/')
        ->and($generated->hash->value())->toBe(hash('sha256', $generated->raw))
        ->and($generated->hash->value())->toHaveLength(64);
});

it('generates a different token on each call', function () {
    $generator = new OpaqueRefreshTokenGenerator();

    expect($generator->generate()->raw)->not->toBe($generator->generate()->raw);
});

it('hashes a raw token deterministically', function () {
    $generator = new OpaqueRefreshTokenGenerator();
    $raw = 'rt_'.str_repeat('a', 64);

    expect($generator->hash($raw)->equals(TokenHash::fromRaw($raw)))->toBeTrue()
        ->and($generator->hash($raw)->value())->not->toBe($raw);
});
