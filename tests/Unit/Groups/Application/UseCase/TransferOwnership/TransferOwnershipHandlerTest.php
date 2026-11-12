<?php

declare(strict_types=1);

use App\Groups\Application\UseCase\TransferOwnership\TransferOwnershipCommand;
use App\Groups\Application\UseCase\TransferOwnership\TransferOwnershipHandler;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Entity\OwnershipTransfer;
use App\Groups\Domain\Group\Event\GroupOwnershipTransferred;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\Repository\OwnershipTransferRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\MembershipRole;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\User\UserActiveReaderInterface;
use App\Shared\Domain\ValueObject\Modality;
use Mockery as m;

/**
 * @return array{0: Group, 1: MemberUserId, 2: \App\Groups\Domain\Group\Entity\Membership}
 */
function groupWithApprovedMember(): array
{
    $owner = MemberUserId::generate();
    $group = Group::create(
        name: GroupName::fromString('Corrida no Parque')->value(),
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
        createdBy: $owner,
    );

    $membership = $group->requestMembership(MemberUserId::generate())->value();
    $group->approveMembership($membership->id(), $owner, 0);

    return [$group, $owner, $membership];
}

function transferOwnershipHandler(
    GroupRepositoryInterface $repository,
    OwnershipTransferRepositoryInterface $transfers,
    UserActiveReaderInterface $activeReader,
    EventDispatcherInterface $dispatcher,
): TransferOwnershipHandler {
    return new TransferOwnershipHandler($repository, $transfers, $activeReader, $dispatcher);
}

it('transfers ownership, records history and dispatches the event', function () {
    [$group, $owner, $membership] = groupWithApprovedMember();

    $repository = m::mock(GroupRepositoryInterface::class);
    $transfers = m::mock(OwnershipTransferRepositoryInterface::class);
    $activeReader = m::mock(UserActiveReaderInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldReceive('save')->once()->with($group);
    $activeReader->shouldReceive('isActive')->once()->with($membership->userId()->value())->andReturnTrue();
    $transfers->shouldReceive('record')->once()->with(m::type(OwnershipTransfer::class));
    $dispatcher->shouldReceive('dispatch')->once()->with(m::type(GroupOwnershipTransferred::class));

    $result = transferOwnershipHandler($repository, $transfers, $activeReader, $dispatcher)(
        new TransferOwnershipCommand(
            groupId: $group->id(),
            membershipId: $membership->id(),
            actorId: $owner,
        ),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($group->ownerId()->equals($membership->userId()))->toBeTrue()
        ->and($group->approvedMembershipOf($membership->userId())?->role())->toBe(MembershipRole::ADMIN);
});

it('rejects a transfer made by a non owner', function () {
    [$group, $owner, $membership] = groupWithApprovedMember();
    $other = MemberUserId::generate();

    $repository = m::mock(GroupRepositoryInterface::class);
    $transfers = m::mock(OwnershipTransferRepositoryInterface::class);
    $activeReader = m::mock(UserActiveReaderInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldNotReceive('save');
    $activeReader->shouldNotReceive('isActive');
    $transfers->shouldNotReceive('record');
    $dispatcher->shouldNotReceive('dispatch');

    $result = transferOwnershipHandler($repository, $transfers, $activeReader, $dispatcher)(
        new TransferOwnershipCommand(
            groupId: $group->id(),
            membershipId: $membership->id(),
            actorId: $other,
        ),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_OWNER_REQUIRED')
        ->and($group->ownerId()->equals($owner))->toBeTrue();
});

it('rejects a transfer to a suspended account', function () {
    [$group, $owner, $membership] = groupWithApprovedMember();

    $repository = m::mock(GroupRepositoryInterface::class);
    $transfers = m::mock(OwnershipTransferRepositoryInterface::class);
    $activeReader = m::mock(UserActiveReaderInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldNotReceive('save');
    $activeReader->shouldReceive('isActive')->once()->with($membership->userId()->value())->andReturnFalse();
    $transfers->shouldNotReceive('record');
    $dispatcher->shouldNotReceive('dispatch');

    $result = transferOwnershipHandler($repository, $transfers, $activeReader, $dispatcher)(
        new TransferOwnershipCommand(
            groupId: $group->id(),
            membershipId: $membership->id(),
            actorId: $owner,
        ),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_MEMBERSHIP_SUSPENDED');
});

it('does nothing when the target is already the owner', function () {
    [$group, $owner, $membership] = groupWithApprovedMember();

    $repository = m::mock(GroupRepositoryInterface::class);
    $transfers = m::mock(OwnershipTransferRepositoryInterface::class);
    $activeReader = m::mock(UserActiveReaderInterface::class);
    $dispatcher = m::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->andReturn($group);
    $repository->shouldNotReceive('save');
    $ownerMembership = $group->approvedMembershipOf($owner);
    assert(null !== $ownerMembership);
    $activeReader->shouldReceive('isActive')->once()->andReturnTrue();
    $transfers->shouldNotReceive('record');
    $dispatcher->shouldNotReceive('dispatch');

    $result = transferOwnershipHandler($repository, $transfers, $activeReader, $dispatcher)(
        new TransferOwnershipCommand(
            groupId: $group->id(),
            membershipId: $ownerMembership->id(),
            actorId: $owner,
        ),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($group->ownerId()->equals($owner))->toBeTrue()
        ->and($membership->role())->toBe(MembershipRole::MEMBER);
});
