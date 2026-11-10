<?php

declare(strict_types=1);

use App\Groups\Application\UseCase\UpdateGroup\UpdateGroupCommand;
use App\Groups\Application\UseCase\UpdateGroup\UpdateGroupHandler;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\GroupUpdated;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\GroupMemberLimit;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Modality;
use Mockery as m;

function makeUpdatableGroup(MemberUserId $admin, ?int $maxMembers = null): Group
{
    return Group::create(
        name: GroupName::fromString('Corrida no Parque')->value(),
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
        createdBy: $admin,
        maxMembers: null === $maxMembers ? null : GroupMemberLimit::fromInt($maxMembers)->value(),
    );
}

function updateGroupCommand(
    Group $group,
    MemberUserId $actor,
    ?int $maxMembers,
    bool $maxMembersProvided,
): UpdateGroupCommand {
    return new UpdateGroupCommand(
        groupId: $group->id(),
        actorId: $actor,
        name: null,
        description: null,
        descriptionProvided: false,
        modality: null,
        communicationLink: null,
        communicationLinkProvided: false,
        maxMembers: $maxMembers,
        maxMembersProvided: $maxMembersProvided,
    );
}

it('updates the member limit', function () {
    $admin = MemberUserId::generate();
    $group = makeUpdatableGroup($admin);

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldReceive('save')->once()->with($group);
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(GroupUpdated::class));

    $result = (new UpdateGroupHandler($repository, $dispatcher))(
        updateGroupCommand($group, $admin, 3, true),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($group->maxMembers()?->value())->toBe(3);
});

it('clears the member limit', function () {
    $admin = MemberUserId::generate();
    $group = makeUpdatableGroup($admin, 5);

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldReceive('save')->once()->with($group);
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(GroupUpdated::class));

    $result = (new UpdateGroupHandler($repository, $dispatcher))(
        updateGroupCommand($group, $admin, null, true),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($group->maxMembers())->toBeNull();
});

it('keeps the current member limit when the field is omitted', function () {
    $admin = MemberUserId::generate();
    $group = makeUpdatableGroup($admin, 5);

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldReceive('save')->once()->with($group);
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(GroupUpdated::class));

    $result = (new UpdateGroupHandler($repository, $dispatcher))(
        updateGroupCommand($group, $admin, null, false),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($group->maxMembers()?->value())->toBe(5);
});

it('rejects a member limit below the approved count', function () {
    $admin = MemberUserId::generate();
    $group = makeUpdatableGroup($admin, 5);

    for ($index = 0; $index < 3; ++$index) {
        $membership = $group->requestMembership(MemberUserId::generate())->value();
        $group->approveMembership($membership->id(), $admin, 0);
    }

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new UpdateGroupHandler($repository, $dispatcher))(
        updateGroupCommand($group, $admin, 3, true),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_MEMBER_LIMIT_BELOW_APPROVED');
});
