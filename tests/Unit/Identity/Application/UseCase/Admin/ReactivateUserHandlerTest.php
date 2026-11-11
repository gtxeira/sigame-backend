<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Application\UseCase\Admin;

use App\Identity\Application\UseCase\Admin\ReactivateUser\ReactivateUserCommand;
use App\Identity\Application\UseCase\Admin\ReactivateUser\ReactivateUserHandler;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Entity\UserSuspension;
use App\Identity\Domain\User\Event\UserReactivated;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\Repository\UserSuspensionRepositoryInterface;
use App\Identity\Domain\User\ValueObject\HashedPassword;
use App\Identity\Domain\User\ValueObject\SuspensionAction;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Email;
use Mockery as m;

function reactivateTestUser(string $email): User
{
    return User::register(
        cpf: null,
        email: Email::fromString($email)->value(),
        password: HashedPassword::fromHash('$2y$04$hash'),
        name: 'Reactivate Test User',
    );
}

it('reactivates a suspended user, records history and publishes the event', function () {
    $target = reactivateTestUser('target@example.com');
    $target->suspend();
    $actor = reactivateTestUser('actor@example.com');
    $actor->grantAdminRole();

    $users = m::mock(UserRepositoryInterface::class);
    $suspensions = m::mock(UserSuspensionRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $users->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $users->shouldReceive('findById')->with($target->id())->andReturn($target);
    $users->shouldReceive('findById')->with($actor->id())->andReturn($actor);
    $users->shouldReceive('save')->once()->with($target);
    $suspensions->shouldReceive('save')->once()->with(m::type(UserSuspension::class));
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(UserReactivated::class));

    $result = (new ReactivateUserHandler($users, $suspensions, $dispatcher))(
        new ReactivateUserCommand(
            userId: $target->id(),
            actorId: $actor->id(),
            reason: 'Revisão concluída.',
        ),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->changed)->toBeTrue()
        ->and($target->isActive())->toBeTrue()
        ->and($result->value()->history?->action())->toBe(SuspensionAction::REACTIVATED)
        ->and($result->value()->history?->reason())->toBe('Revisão concluída.');
});

it('returns changed false when the user is already active', function () {
    $target = reactivateTestUser('already-active@example.com');
    $actor = reactivateTestUser('actor@example.com');
    $actor->grantAdminRole();

    $users = m::mock(UserRepositoryInterface::class);
    $suspensions = m::mock(UserSuspensionRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $users->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $users->shouldReceive('findById')->with($target->id())->andReturn($target);
    $users->shouldNotReceive('save');
    $suspensions->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new ReactivateUserHandler($users, $suspensions, $dispatcher))(
        new ReactivateUserCommand(
            userId: $target->id(),
            actorId: $actor->id(),
            reason: 'Motivo válido.',
        ),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->changed)->toBeFalse()
        ->and($result->value()->history)->toBeNull();
});

it('records a null reason when the optional reason is blank', function () {
    $target = reactivateTestUser('blank-reason@example.com');
    $target->suspend();
    $actor = reactivateTestUser('actor@example.com');
    $actor->grantAdminRole();

    $users = m::mock(UserRepositoryInterface::class);
    $suspensions = m::mock(UserSuspensionRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $users->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $users->shouldReceive('findById')->with($target->id())->andReturn($target);
    $users->shouldReceive('findById')->with($actor->id())->andReturn($actor);
    $users->shouldReceive('save')->once()->with($target);
    $suspensions->shouldReceive('save')->once();
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(UserReactivated::class));

    $result = (new ReactivateUserHandler($users, $suspensions, $dispatcher))(
        new ReactivateUserCommand(
            userId: $target->id(),
            actorId: $actor->id(),
            reason: '   ',
        ),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->history?->reason())->toBeNull();
});

it('rejects an invalid reason before changing the account', function () {
    $target = reactivateTestUser('invalid-reason@example.com');
    $target->suspend();
    $actor = reactivateTestUser('actor@example.com');
    $actor->grantAdminRole();

    $users = m::mock(UserRepositoryInterface::class);
    $suspensions = m::mock(UserSuspensionRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $users->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $users->shouldReceive('findById')->with($target->id())->andReturn($target);
    $users->shouldNotReceive('save');
    $suspensions->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new ReactivateUserHandler($users, $suspensions, $dispatcher))(
        new ReactivateUserCommand(
            userId: $target->id(),
            actorId: $actor->id(),
            reason: 'ab',
        ),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('STRING_TOO_SHORT')
        ->and($target->isSuspended())->toBeTrue();
});

it('fails when the target account does not exist', function () {
    $target = reactivateTestUser('missing@example.com');
    $actor = reactivateTestUser('actor@example.com');
    $actor->grantAdminRole();

    $users = m::mock(UserRepositoryInterface::class);
    $suspensions = m::mock(UserSuspensionRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $users->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $users->shouldReceive('findById')->with($target->id())->andReturn(null);
    $users->shouldNotReceive('save');
    $suspensions->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new ReactivateUserHandler($users, $suspensions, $dispatcher))(
        new ReactivateUserCommand(
            userId: $target->id(),
            actorId: $actor->id(),
        ),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('USER_NOT_FOUND');
});

it('fails when the actor account does not exist', function () {
    $target = reactivateTestUser('target@example.com');
    $target->suspend();
    $actor = reactivateTestUser('missing-actor@example.com');

    $users = m::mock(UserRepositoryInterface::class);
    $suspensions = m::mock(UserSuspensionRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $users->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $users->shouldReceive('findById')->with($target->id())->andReturn($target);
    $users->shouldReceive('findById')->with($actor->id())->andReturn(null);
    $users->shouldNotReceive('save');
    $suspensions->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new ReactivateUserHandler($users, $suspensions, $dispatcher))(
        new ReactivateUserCommand(
            userId: $target->id(),
            actorId: $actor->id(),
        ),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('USER_NOT_FOUND');
});
