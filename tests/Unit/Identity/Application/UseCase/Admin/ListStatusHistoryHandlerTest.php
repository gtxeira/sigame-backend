<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Application\UseCase\Admin;

use App\Identity\Application\UseCase\Admin\ListStatusHistory\ListStatusHistoryHandler;
use App\Identity\Application\UseCase\Admin\ListStatusHistory\ListStatusHistoryQuery;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Entity\UserSuspension;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\Repository\UserSuspensionRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\SuspensionAction;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Instant;
use Mockery as m;

function historyTestUser(string $email): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('$2y$04$hash'),
        name: 'History Test User',
    );
}

it('returns the page and total for the requested user', function () {
    $target = historyTestUser('history@example.com');
    $actor = historyTestUser('actor@example.com');
    $actor->grantAdminRole();
    $suspension = UserSuspension::record(
        user: $target,
        actor: $actor,
        action: SuspensionAction::SUSPENDED,
        reason: 'Motivo válido.',
        occurredAt: Instant::now(),
    );

    $users = m::mock(UserRepositoryInterface::class);
    $suspensions = m::mock(UserSuspensionRepositoryInterface::class);

    $users->shouldReceive('findById')->once()->with($target->id())->andReturn($target);
    $suspensions->shouldReceive('findByUser')->once()->with($target->id(), 10, 10)->andReturn([$suspension]);
    $suspensions->shouldReceive('countByUser')->once()->with($target->id())->andReturn(11);

    $result = (new ListStatusHistoryHandler($users, $suspensions))(
        new ListStatusHistoryQuery(
            userId: $target->id(),
            page: 2,
            perPage: 10,
        ),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->items)->toBe([$suspension])
        ->and($result->value()->total)->toBe(11);
});

it('clamps page and per page to the allowed range', function () {
    $target = historyTestUser('history-clamped@example.com');

    $users = m::mock(UserRepositoryInterface::class);
    $suspensions = m::mock(UserSuspensionRepositoryInterface::class);

    $users->shouldReceive('findById')->once()->with($target->id())->andReturn($target);
    $suspensions->shouldReceive('findByUser')->once()->with($target->id(), 30, 0)->andReturn([]);
    $suspensions->shouldReceive('countByUser')->once()->with($target->id())->andReturn(0);

    $result = (new ListStatusHistoryHandler($users, $suspensions))(
        new ListStatusHistoryQuery(
            userId: $target->id(),
            page: 0,
            perPage: 100,
        ),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->items)->toBe([])
        ->and($result->value()->total)->toBe(0);
});

it('fails when the account does not exist', function () {
    $userId = UserId::generate();

    $users = m::mock(UserRepositoryInterface::class);
    $suspensions = m::mock(UserSuspensionRepositoryInterface::class);

    $users->shouldReceive('findById')->once()->with($userId)->andReturn(null);
    $suspensions->shouldNotReceive('findByUser');
    $suspensions->shouldNotReceive('countByUser');

    $result = (new ListStatusHistoryHandler($users, $suspensions))(
        new ListStatusHistoryQuery(userId: $userId),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('USER_NOT_FOUND');
});
