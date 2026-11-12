<?php

declare(strict_types=1);

use App\Groups\Application\UseCase\ChangeMembershipRole\ChangeMembershipRoleCommand;
use App\Groups\Application\UseCase\ChangeMembershipRole\ChangeMembershipRoleHandler;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\MembershipPromoted;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\MembershipRole;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Modality;
use Mockery as m;

/**
 * @return array{0: Group, 1: MemberUserId, 2: \App\Groups\Domain\Group\Entity\Membership}
 */
function groupWithMember(): array
{
    $admin = MemberUserId::generate();
    $group = Group::create(
        name: GroupName::fromString('Corrida no Parque')->value(),
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
        createdBy: $admin,
    );

    $membership = $group->requestMembership(MemberUserId::generate())->value();
    $group->approveMembership($membership->id(), $admin, 0);

    return [$group, $admin, $membership];
}

it('promotes an approved member to admin', function () {
    [$group, $admin, $membership] = groupWithMember();

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldReceive('save')->once()->with($group);
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(MembershipPromoted::class));

    $result = (new ChangeMembershipRoleHandler($repository, $dispatcher))(new ChangeMembershipRoleCommand(
        groupId: $group->id(),
        membershipId: $membership->id(),
        actorId: $admin,
        role: MembershipRole::ADMIN,
    ));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value()->role())->toBe(MembershipRole::ADMIN)
        ->and($group->approvedAdminCount())->toBe(2);
});

it('does not demote the group owner', function () {
    $admin = MemberUserId::generate();
    $group = Group::create(
        name: GroupName::fromString('Corrida no Parque')->value(),
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
        createdBy: $admin,
    );
    $adminMembership = $group->approvedMembershipOf($admin);
    assert(null !== $adminMembership);

    $repository = m::mock(GroupRepositoryInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldNotReceive('save');
    $dispatcher->shouldNotReceive('dispatch');

    $result = (new ChangeMembershipRoleHandler($repository, $dispatcher))(new ChangeMembershipRoleCommand(
        groupId: $group->id(),
        membershipId: $adminMembership->id(),
        actorId: $admin,
        role: MembershipRole::MEMBER,
    ));

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_OWNER_TRANSFER_REQUIRED');
});
