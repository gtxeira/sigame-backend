<?php

declare(strict_types=1);

use App\Identity\Domain\User\Exception\UserAlreadyActiveException;
use App\Identity\Domain\User\Exception\UserAlreadySuspendedException;
use App\Identity\Domain\User\ValueObject\AccessState;
use App\Identity\Domain\User\ValueObject\UserRole;
use App\Identity\Domain\User\ValueObject\UserStatus;

it('starts active with the user role', function () {
    $access = AccessState::initial();

    expect($access->role())->toBe(UserRole::USER)
        ->and($access->status())->toBe(UserStatus::ACTIVE)
        ->and($access->isActive())->toBeTrue()
        ->and($access->isSuspended())->toBeFalse()
        ->and($access->canAuthenticate())->toBeTrue();
});

it('suspends the account and blocks authentication', function () {
    $access = AccessState::initial()->suspend();

    expect($access->status())->toBe(UserStatus::SUSPENDED)
        ->and($access->isSuspended())->toBeTrue()
        ->and($access->isActive())->toBeFalse()
        ->and($access->canAuthenticate())->toBeFalse()
        ->and($access->role())->toBe(UserRole::USER);
});

it('keeps the previous state untouched when suspending', function () {
    $active = AccessState::initial();
    $suspended = $active->suspend();

    expect($active->isActive())->toBeTrue()
        ->and($suspended)->not->toBe($active);
});

it('reactivates a suspended account', function () {
    $access = AccessState::initial()->suspend()->reactivate();

    expect($access->status())->toBe(UserStatus::ACTIVE)
        ->and($access->isActive())->toBeTrue()
        ->and($access->canAuthenticate())->toBeTrue();
});

it('throws when suspending an already suspended account', function () {
    AccessState::initial()->suspend()->suspend();
})->throws(UserAlreadySuspendedException::class);

it('throws when reactivating an already active account', function () {
    AccessState::initial()->reactivate();
})->throws(UserAlreadyActiveException::class);
