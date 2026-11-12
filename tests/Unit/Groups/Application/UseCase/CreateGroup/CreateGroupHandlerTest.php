<?php

declare(strict_types=1);

use App\Groups\Application\UseCase\CreateGroup\CreateGroupCommand;
use App\Groups\Application\UseCase\CreateGroup\CreateGroupHandler;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\GroupCreated;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\User\UserLevelReaderInterface;
use App\Shared\Domain\ValueObject\Modality;
use Mockery as m;

it('creates a group for a user at level 2', function () {
    $actor = MemberUserId::generate();
    $repository = m::mock(GroupRepositoryInterface::class);
    $levelReader = m::mock(UserLevelReaderInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $levelReader->shouldReceive('levelOf')->once()->with($actor->value())->andReturn(2);
    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('lockUser')->once()->with(m::on(
        static fn (MemberUserId $id): bool => $id->equals($actor),
    ));
    $repository->shouldReceive('countApprovedMemberships')->once()->andReturn(0);
    $repository->shouldReceive('existsByOwnerAndName')->once()->andReturnFalse();
    $repository->shouldReceive('countFormingOwnedBy')->once()->andReturn(0);
    $repository->shouldReceive('save')->once()->with(m::type(Group::class));
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(GroupCreated::class));

    $result = (new CreateGroupHandler($repository, $levelReader, $dispatcher))(new CreateGroupCommand(
        actorId: $actor,
        name: 'Corrida no Parque',
        description: 'Encontros aos sábados.',
        modality: Modality::RUNNING,
        communicationLink: null,
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->createdBy()->equals($actor))->toBeTrue();
});

it('creates a group with a custom member limit', function () {
    $actor = MemberUserId::generate();
    $repository = m::mock(GroupRepositoryInterface::class);
    $levelReader = m::mock(UserLevelReaderInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $levelReader->shouldReceive('levelOf')->once()->andReturn(2);
    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('lockUser')->once();
    $repository->shouldReceive('countApprovedMemberships')->once()->andReturn(0);
    $repository->shouldReceive('existsByOwnerAndName')->once()->andReturnFalse();
    $repository->shouldReceive('countFormingOwnedBy')->once()->andReturn(0);
    $repository->shouldReceive('save')->once()->with(m::on(
        static fn (Group $group): bool => 3 === $group->maxMembers()?->value(),
    ));
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(GroupCreated::class));

    $result = (new CreateGroupHandler($repository, $levelReader, $dispatcher))(new CreateGroupCommand(
        actorId: $actor,
        name: 'Corrida no Parque',
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
        maxMembers: 3,
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->maxMembers()?->value())->toBe(3);
});

it('rejects a member limit below three', function () {
    $actor = MemberUserId::generate();
    $repository = m::mock(GroupRepositoryInterface::class);
    $levelReader = m::mock(UserLevelReaderInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldNotReceive('transactional');
    $levelReader->shouldNotReceive('levelOf');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new CreateGroupHandler($repository, $levelReader, $dispatcher))(new CreateGroupCommand(
        actorId: $actor,
        name: 'Corrida no Parque',
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
        maxMembers: 2,
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('NUMBER_OUT_OF_RANGE');
});

it('rejects group creation below level 2', function () {
    $actor = MemberUserId::generate();
    $repository = m::mock(GroupRepositoryInterface::class);
    $levelReader = m::mock(UserLevelReaderInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $levelReader->shouldReceive('levelOf')->once()->andReturn(1);
    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldNotReceive('lockUser');
    $repository->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new CreateGroupHandler($repository, $levelReader, $dispatcher))(new CreateGroupCommand(
        actorId: $actor,
        name: 'Corrida no Parque',
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_LEVEL_INSUFFICIENT');
});

it('rejects group creation when the user already belongs to five groups', function () {
    $actor = MemberUserId::generate();
    $repository = m::mock(GroupRepositoryInterface::class);
    $levelReader = m::mock(UserLevelReaderInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $levelReader->shouldReceive('levelOf')->once()->andReturn(2);
    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('lockUser')->once();
    $repository->shouldReceive('existsByOwnerAndName')->once()->andReturnFalse();
    $repository->shouldReceive('countApprovedMemberships')->once()->andReturn(5);
    $repository->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new CreateGroupHandler($repository, $levelReader, $dispatcher))(new CreateGroupCommand(
        actorId: $actor,
        name: 'Corrida no Parque',
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_LIMIT_REACHED');
});

it('rejects group creation when the user already has a forming group', function () {
    $actor = MemberUserId::generate();
    $repository = m::mock(GroupRepositoryInterface::class);
    $levelReader = m::mock(UserLevelReaderInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $levelReader->shouldReceive('levelOf')->once()->andReturn(2);
    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('lockUser')->once();
    $repository->shouldReceive('existsByOwnerAndName')->once()->andReturnFalse();
    $repository->shouldReceive('countApprovedMemberships')->once()->andReturn(1);
    $repository->shouldReceive('countFormingOwnedBy')->once()->andReturn(1);
    $repository->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new CreateGroupHandler($repository, $levelReader, $dispatcher))(new CreateGroupCommand(
        actorId: $actor,
        name: 'Corrida no Parque',
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_FORMING_LIMIT_REACHED');
});

it('rejects group creation when the user already created the same name and modality', function () {
    $actor = MemberUserId::generate();
    $repository = m::mock(GroupRepositoryInterface::class);
    $levelReader = m::mock(UserLevelReaderInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $levelReader->shouldReceive('levelOf')->once()->andReturn(2);
    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('lockUser')->once();
    $repository->shouldReceive('existsByOwnerAndName')->once()->andReturnTrue();
    $repository->shouldNotReceive('countApprovedMemberships');
    $repository->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new CreateGroupHandler($repository, $levelReader, $dispatcher))(new CreateGroupCommand(
        actorId: $actor,
        name: 'Corrida no Parque',
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_DUPLICATE');
});

it('validates group fields before touching persistence', function () {
    $actor = MemberUserId::generate();
    $repository = m::mock(GroupRepositoryInterface::class);
    $levelReader = m::mock(UserLevelReaderInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldNotReceive('transactional');
    $levelReader->shouldNotReceive('levelOf');

    $result = (new CreateGroupHandler($repository, $levelReader, $dispatcher))(new CreateGroupCommand(
        actorId: $actor,
        name: 'ab',
        description: null,
        modality: Modality::RUNNING,
        communicationLink: 'ftp://invalid.example.com',
    ));

    expect($result->isFailure())->toBeTrue()
        ->and(array_map(static fn ($error): string => $error->code, $result->errors()))
        ->toBe(['STRING_TOO_SHORT', 'INVALID_FORMAT']);
});
