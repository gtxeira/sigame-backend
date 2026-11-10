<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Application\UseCase\Admin;

use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Identity\Application\UseCase\Admin\SuspendUser\SuspendUserCommand;
use App\Identity\Application\UseCase\Admin\SuspendUser\SuspendUserHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserSuspended;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\Repository\UserSuspensionRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Email;
use Mockery as m;

function adminTestUser(string $email): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('$2y$04$hash'),
        name: 'Admin Test User',
    );
}

it('suspends a regular user, records history, revokes sessions and publishes the event', function () {
    $target = adminTestUser('target@example.com');
    $actor = adminTestUser('actor@example.com');
    $actor->grantAdminRole();

    $users = m::mock(UserRepositoryInterface::class);
    $suspensions = m::mock(UserSuspensionRepositoryInterface::class);
    $tokens = m::mock(TokenPairIssuerInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $users->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $users->shouldReceive('findById')->with($target->id())->andReturn($target);
    $users->shouldReceive('findById')->with($actor->id())->andReturn($actor);
    $users->shouldReceive('save')->once()->with($target);
    $suspensions->shouldReceive('save')->once();
    $tokens->shouldReceive('revokeAllSessions')->once()->with($target->id());
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(UserSuspended::class));

    $result = (new SuspendUserHandler($users, $suspensions, $tokens, $dispatcher))(
        new SuspendUserCommand(
            userId: $target->id(),
            actorId: $actor->id(),
            reason: 'Descumprimento das regras.',
        ),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->changed)->toBeTrue()
        ->and($target->isSuspended())->toBeTrue()
        ->and($result->value()->history?->reason())->toBe('Descumprimento das regras.');
});

it('refuses to suspend an admin account', function () {
    $target = adminTestUser('admin-target@example.com');
    $target->grantAdminRole();
    $actor = adminTestUser('actor@example.com');
    $actor->grantAdminRole();

    $users = m::mock(UserRepositoryInterface::class);
    $suspensions = m::mock(UserSuspensionRepositoryInterface::class);
    $tokens = m::mock(TokenPairIssuerInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $users->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $users->shouldReceive('findById')->with($target->id())->andReturn($target);
    $users->shouldNotReceive('save');
    $suspensions->shouldNotReceive('save');
    $tokens->shouldNotReceive('revokeAllSessions');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new SuspendUserHandler($users, $suspensions, $tokens, $dispatcher))(
        new SuspendUserCommand(
            userId: $target->id(),
            actorId: $actor->id(),
            reason: 'Tentativa indevida.',
        ),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('CANNOT_SUSPEND_ADMIN');
});

it('returns changed false when the user is already suspended', function () {
    $target = adminTestUser('already@example.com');
    $target->suspend();
    $actor = adminTestUser('actor@example.com');
    $actor->grantAdminRole();

    $users = m::mock(UserRepositoryInterface::class);
    $suspensions = m::mock(UserSuspensionRepositoryInterface::class);
    $tokens = m::mock(TokenPairIssuerInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $users->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $users->shouldReceive('findById')->with($target->id())->andReturn($target);
    $users->shouldNotReceive('save');
    $suspensions->shouldNotReceive('save');
    $tokens->shouldNotReceive('revokeAllSessions');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new SuspendUserHandler($users, $suspensions, $tokens, $dispatcher))(
        new SuspendUserCommand(
            userId: $target->id(),
            actorId: $actor->id(),
            reason: 'Motivo válido.',
        ),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->changed)->toBeFalse()
        ->and($result->value()->history)->toBeNull();
});
