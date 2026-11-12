<?php

declare(strict_types=1);

use App\Groups\Application\UseCase\ApproveMembership\ApproveMembershipCommand;
use App\Groups\Application\UseCase\ApproveMembership\ApproveMembershipHandler;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\GroupActivated;
use App\Groups\Domain\Group\Event\MembershipApproved;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\GroupMemberLimit;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\User\UserActiveReaderInterface;
use App\Shared\Domain\ValueObject\Modality;
use Mockery as m;

/**
 * @return array{0: Group, 1: MemberUserId, 2: list<\App\Groups\Domain\Group\Entity\Membership>, 3: list<\App\Groups\Domain\Group\Entity\Membership>}
 */
function groupWithPendingMembers(int $approved, int $pending, ?int $maxMembers = null): array
{
    $admin = MemberUserId::generate();
    $group = Group::create(
        name: GroupName::fromString('Corrida no Parque')->value(),
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
        createdBy: $admin,
        maxMembers: null === $maxMembers ? null : GroupMemberLimit::fromInt($maxMembers)->value(),
    );

    $approvedMembers = [];

    for ($index = 0; $index < $approved; ++$index) {
        $membership = $group->requestMembership(MemberUserId::generate())->value();
        $group->approveMembership($membership->id(), $admin, 0);
        $approvedMembers[] = $membership;
    }

    $pendingMembers = [];

    for ($index = 0; $index < $pending; ++$index) {
        $pendingMembers[] = $group->requestMembership(MemberUserId::generate())->value();
    }

    return [$group, $admin, $approvedMembers, $pendingMembers];
}

it('approves a pending request and activates the group at three members', function () {
    [$group, $admin, , $pending] = groupWithPendingMembers(approved: 1, pending: 2);
    $target = $pending[1];

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);
    $activeReader = m::mock(UserActiveReaderInterface::class);
    $activeReader->shouldReceive('isActive')->andReturnTrue();

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldReceive('lockUser')->once();
    $repository->shouldReceive('countApprovedMemberships')->once()->andReturn(0);
    $repository->shouldReceive('save')->once()->with($group);
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(MembershipApproved::class));
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(GroupActivated::class));

    $result = (new ApproveMembershipHandler($repository, $activeReader, $dispatcher))(new ApproveMembershipCommand(
        groupId: $group->id(),
        membershipId: $target->id(),
        actorId: $admin,
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->isApproved())->toBeTrue()
        ->and($group->approvedCount())->toBe(3)
        ->and($group->isActive())->toBeTrue();
});

it('rejects approval by a non admin', function () {
    [$group, , , $pending] = groupWithPendingMembers(approved: 0, pending: 1);
    $target = $pending[0];

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);
    $activeReader = m::mock(UserActiveReaderInterface::class);
    $activeReader->shouldReceive('isActive')->andReturnTrue();

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldReceive('lockUser')->once();
    $repository->shouldReceive('countApprovedMemberships')->once()->andReturn(0);
    $repository->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new ApproveMembershipHandler($repository, $activeReader, $dispatcher))(new ApproveMembershipCommand(
        groupId: $group->id(),
        membershipId: $target->id(),
        actorId: $target->userId(),
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_ADMIN_REQUIRED');
});

it('rejects approval when the group is full', function () {
    [$group, $admin, , $pending] = groupWithPendingMembers(approved: 4, pending: 1, maxMembers: 5);
    $target = $pending[0];

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);
    $activeReader = m::mock(UserActiveReaderInterface::class);
    $activeReader->shouldReceive('isActive')->andReturnTrue();

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldReceive('lockUser')->once();
    $repository->shouldReceive('countApprovedMemberships')->once()->andReturn(0);
    $repository->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new ApproveMembershipHandler($repository, $activeReader, $dispatcher))(new ApproveMembershipCommand(
        groupId: $group->id(),
        membershipId: $target->id(),
        actorId: $admin,
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_FULL');
});

it('rejects approval when the user reached the five group limit', function () {
    [$group, $admin, , $pending] = groupWithPendingMembers(approved: 0, pending: 1);
    $target = $pending[0];

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);
    $activeReader = m::mock(UserActiveReaderInterface::class);
    $activeReader->shouldReceive('isActive')->andReturnTrue();

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldReceive('lockUser')->once();
    $repository->shouldReceive('countApprovedMemberships')->once()->andReturn(5);
    $repository->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new ApproveMembershipHandler($repository, $activeReader, $dispatcher))(new ApproveMembershipCommand(
        groupId: $group->id(),
        membershipId: $target->id(),
        actorId: $admin,
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_LIMIT_REACHED');
});
