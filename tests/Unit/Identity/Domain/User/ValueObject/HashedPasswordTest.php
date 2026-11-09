<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\HashedPassword;

it('creates a hashed password from a non-empty hash', function () {
    $hashedPassword = HashedPassword::fromHash('hashed_password_123');

    expect($hashedPassword->value())->toBe('hashed_password_123')
        ->and((string) $hashedPassword)->toBe('hashed_password_123');
});

it('rejects an empty hash', function () {
    HashedPassword::fromHash('');
})->throws(LogicException::class);
