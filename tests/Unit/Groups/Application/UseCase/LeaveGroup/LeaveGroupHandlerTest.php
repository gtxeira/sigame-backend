<?php

declare(strict_types=1);

use App\Groups\Application\UseCase\LeaveGroup\LeaveGroupCommand;
use App\Groups\Application\UseCase\LeaveGroup\LeaveGroupHandler;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\MembershipLeft;
use App\Groups\Domain\Group\Event\MembershipRequestWithdrawn;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Modality;
use Mockery as m;

function leaveGroupFixture(MemberUserId $admin): Group
{
    return Group::create(
        name: GroupName::fromString('Corrida no Parque')->value(),
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
        createdBy: $admin,
    );
}

it('withdraws a pending request and dispatches the withdrawal event', function () {
    $admin = MemberUserId::generate();
    $member = MemberUserId::generate();
    $group = leaveGroupFixture($admin);
    $group->requestMembership($member);

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldReceive('save')->once()->with($group);
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(MembershipRequestWithdrawn::class));
    $dispatcher->shouldNotReceive('dispatch')->with(m::type(MembershipLeft::class));

    $result = (new LeaveGroupHandler($repository, $dispatcher))(new LeaveGroupCommand(
        groupId: $group->id(),
        actorId: $member,
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBeTrue();
});

it('removes an approved membership and dispatches the leave event', function () {
    $admin = MemberUserId::generate();
    $member = MemberUserId::generate();
    $group = leaveGroupFixture($admin);
    $membership = $group->requestMembership($member)->value();
    $group->approveMembership($membership->id(), $admin, 0);

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldReceive('save')->once()->with($group);
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(MembershipLeft::class));

    $result = (new LeaveGroupHandler($repository, $dispatcher))(new LeaveGroupCommand(
        groupId: $group->id(),
        actorId: $member,
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBeTrue()
        ->and($membership->status()->value)->toBe('removed');
});

it('is idempotent when the user has no active membership', function () {
    $admin = MemberUserId::generate();
    $group = leaveGroupFixture($admin);

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new LeaveGroupHandler($repository, $dispatcher))(new LeaveGroupCommand(
        groupId: $group->id(),
        actorId: MemberUserId::generate(),
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBeFalse();
});

it('rejects leaving when the user is the last admin', function () {
    $admin = MemberUserId::generate();
    $group = leaveGroupFixture($admin);

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new LeaveGroupHandler($repository, $dispatcher))(new LeaveGroupCommand(
        groupId: $group->id(),
        actorId: $admin,
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_LAST_ADMIN_REQUIRED');
});
