<?php

declare(strict_types=1);

use App\Identity\Domain\RefreshToken\ValueObject\TokenHash;

it('creates a hash from a raw token', function () {
    $hash = TokenHash::fromRaw('raw-token');

    expect($hash->value())->toBe(hash('sha256', 'raw-token'));
});

it('normalizes a hash from string', function () {
    $hash = TokenHash::fromHash(strtoupper(hash('sha256', 'raw-token')));

    expect($hash->value())->toBe(hash('sha256', 'raw-token'));
});

it('rejects an invalid hash', function () {
    TokenHash::fromHash('invalid');
})->throws(LogicException::class);
