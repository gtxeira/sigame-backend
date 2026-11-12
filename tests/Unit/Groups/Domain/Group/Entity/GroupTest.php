<?php

declare(strict_types=1);

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\ValueObject\GroupDescription;
use App\Groups\Domain\Group\ValueObject\GroupMemberLimit;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Groups\Domain\Group\ValueObject\MembershipRole;
use App\Groups\Domain\Group\ValueObject\MembershipStatus;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Domain\Group\ValueObject\RemovalReason;
use App\Shared\Domain\ValueObject\Modality;

function makeGroup(MemberUserId $admin, ?int $maxMembers = null): Group
{
    return Group::create(
        name: GroupName::fromString('Corrida no Parque')->value(),
        description: GroupDescription::fromString('Encontros aos sábados.')->value(),
        modality: Modality::RUNNING,
        communicationLink: null,
        createdBy: $admin,
        maxMembers: null === $maxMembers ? null : GroupMemberLimit::fromInt($maxMembers)->value(),
    );
}

it('creates a group with the creator as the first admin', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin);

    $adminMembership = $group->approvedMembershipOf($admin);

    expect($group->createdBy()->equals($admin))->toBeTrue()
        ->and($group->ownerId()->equals($admin))->toBeTrue()
        ->and($group->status())->toBe(GroupStatus::FORMING)
        ->and($group->approvedCount())->toBe(1)
        ->and($group->approvedAdminCount())->toBe(1)
        ->and($adminMembership?->role())->toBe(MembershipRole::ADMIN)
        ->and($adminMembership?->status())->toBe(MembershipStatus::APPROVED);
});

it('activates the group at three approved members and becomes full at five', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin, 5);

    $members = [];

    for ($index = 0; $index < 4; ++$index) {
        $member = MemberUserId::generate();
        $members[] = $group->requestMembership($member)->value();

        $approval = $group->approveMembership($members[$index]->id(), $admin, 0);
        expect($approval->isSuccess())->toBeTrue();
    }

    expect($group->approvedCount())->toBe(5)
        ->and($group->status())->toBe(GroupStatus::FULL);

    $extra = $group->requestMembership(MemberUserId::generate())->value();
    $rejected = $group->approveMembership($extra->id(), $admin, 0);

    expect($rejected->isFailure())->toBeTrue()
        ->and($rejected->errors()[0]->code)->toBe('GROUP_FULL');
});

it('keeps a group without a limit active beyond five members', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin);

    for ($index = 0; $index < 6; ++$index) {
        $membership = $group->requestMembership(MemberUserId::generate())->value();
        $approval = $group->approveMembership($membership->id(), $admin, 0);

        expect($approval->isSuccess())->toBeTrue();
    }

    expect($group->approvedCount())->toBe(7)
        ->and($group->maxMembers())->toBeNull()
        ->and($group->status())->toBe(GroupStatus::ACTIVE);
});

it('becomes full at a custom limit of three members', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin, 3);

    for ($index = 0; $index < 2; ++$index) {
        $membership = $group->requestMembership(MemberUserId::generate())->value();
        $group->approveMembership($membership->id(), $admin, 0);
    }

    expect($group->approvedCount())->toBe(3)
        ->and($group->status())->toBe(GroupStatus::FULL);

    $extra = $group->requestMembership(MemberUserId::generate())->value();
    $rejected = $group->approveMembership($extra->id(), $admin, 0);

    expect($rejected->isFailure())->toBeTrue()
        ->and($rejected->errors()[0]->code)->toBe('GROUP_FULL');
});

it('changes the member limit and rejects values below the approved count', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin, 5);

    for ($index = 0; $index < 3; ++$index) {
        $membership = $group->requestMembership(MemberUserId::generate())->value();
        $group->approveMembership($membership->id(), $admin, 0);
    }

    $belowApproved = $group->changeDetails(
        name: $group->name(),
        description: null,
        modality: $group->modality(),
        communicationLink: null,
        maxMembers: GroupMemberLimit::fromInt(3)->value(),
        actor: $admin,
    );

    expect($belowApproved->isFailure())->toBeTrue()
        ->and($belowApproved->errors()[0]->code)->toBe('GROUP_MEMBER_LIMIT_BELOW_APPROVED');

    $raise = $group->changeDetails(
        name: $group->name(),
        description: null,
        modality: $group->modality(),
        communicationLink: null,
        maxMembers: GroupMemberLimit::fromInt(8)->value(),
        actor: $admin,
    );

    expect($raise->isSuccess())->toBeTrue()
        ->and($group->maxMembers()?->value())->toBe(8);

    $unlimited = $group->changeDetails(
        name: $group->name(),
        description: null,
        modality: $group->modality(),
        communicationLink: null,
        maxMembers: null,
        actor: $admin,
    );

    expect($unlimited->isSuccess())->toBeTrue()
        ->and($group->maxMembers())->toBeNull();
});

it('rejects a duplicate pending request', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin);
    $member = MemberUserId::generate();

    $group->requestMembership($member);
    $duplicate = $group->requestMembership($member);

    expect($duplicate->isFailure())->toBeTrue()
        ->and($duplicate->errors()[0]->code)->toBe('GROUP_MEMBERSHIP_ALREADY_EXISTS');
});

it('allows a new request after a rejection', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin);
    $member = MemberUserId::generate();

    $first = $group->requestMembership($member)->value();
    $group->rejectMembership($first->id(), $admin);

    $second = $group->requestMembership($member);

    expect($second->isSuccess())->toBeTrue()
        ->and($second->value()->status())->toBe(MembershipStatus::PENDING);
});

it('records the removal author, date and reason', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin);
    $member = MemberUserId::generate();

    $membership = $group->requestMembership($member)->value();
    $group->approveMembership($membership->id(), $admin, 0);

    $reason = RemovalReason::fromString('Descumprimento das regras.')->value();
    $removed = $group->removeMembership($membership->id(), $admin, $reason);

    expect($removed->isSuccess())->toBeTrue();

    $removedMembership = $removed->value();

    expect($removedMembership->status())->toBe(MembershipStatus::REMOVED)
        ->and($removedMembership->removedBy()?->equals($admin))->toBeTrue()
        ->and($removedMembership->removedAt())->not->toBeNull()
        ->and($removedMembership->removalReason()?->value())->toBe('Descumprimento das regras.');
});

it('supports multiple admins but never demotes the owner', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin);
    $member = MemberUserId::generate();

    $membership = $group->requestMembership($member)->value();
    $group->approveMembership($membership->id(), $admin, 0);

    $promotion = $group->promoteToAdmin($membership->id(), $admin);

    expect($promotion->isSuccess())->toBeTrue()
        ->and($group->approvedAdminCount())->toBe(2);

    $demotion = $group->demoteToMember($membership->id(), $admin);
    expect($demotion->isSuccess())->toBeTrue()
        ->and($group->approvedAdminCount())->toBe(1);

    $lastAdminMembership = $group->approvedMembershipOf($admin);
    assert(null !== $lastAdminMembership);

    $rejected = $group->demoteToMember($lastAdminMembership->id(), $admin);

    expect($rejected->isFailure())->toBeTrue()
        ->and($rejected->errors()[0]->code)->toBe('GROUP_OWNER_TRANSFER_REQUIRED');
});

it('does not remove the owner', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin);
    $membership = $group->approvedMembershipOf($admin);
    assert(null !== $membership);

    $result = $group->removeMembership(
        $membership->id(),
        $admin,
        RemovalReason::fromString('Saída.')->value(),
    );

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_OWNER_TRANSFER_REQUIRED');
});

it('keeps a single supervisor and clears the previous one', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin);

    $first = $group->requestMembership(MemberUserId::generate())->value();
    $second = $group->requestMembership(MemberUserId::generate())->value();
    $group->approveMembership($first->id(), $admin, 0);
    $group->approveMembership($second->id(), $admin, 0);

    $group->designateSupervisor($first->id(), $admin);
    $group->designateSupervisor($second->id(), $admin);

    expect($first->isSupervisor())->toBeFalse()
        ->and($second->isSupervisor())->toBeTrue()
        ->and($group->supervisor()?->id()->equals($second->id()))->toBeTrue();

    $clear = $group->clearSupervisor($admin);

    expect($clear->isSuccess())->toBeTrue()
        ->and($clear->value())->toBeTrue()
        ->and($group->supervisor())->toBeNull();
});

it('rejects membership management by a non admin', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin);
    $member = MemberUserId::generate();
    $pending = $group->requestMembership($member)->value();

    $result = $group->approveMembership($pending->id(), $member, 0);

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_ADMIN_REQUIRED');
});

it('only designates an approved member as supervisor', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin);
    $pending = $group->requestMembership(MemberUserId::generate())->value();

    $result = $group->designateSupervisor($pending->id(), $admin);

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('GROUP_SUPERVISOR_NOT_MEMBER');
});

it('transfers ownership to an approved member and promotes them to admin', function () {
    $owner = MemberUserId::generate();
    $group = makeGroup($owner);
    $member = MemberUserId::generate();

    $membership = $group->requestMembership($member)->value();
    $group->approveMembership($membership->id(), $owner, 0);

    $transfer = $group->transferOwnership($member, $owner);

    expect($transfer->isSuccess())->toBeTrue()
        ->and($transfer->value())->toBeTrue()
        ->and($group->ownerId()->equals($member))->toBeTrue()
        ->and($group->createdBy()->equals($owner))->toBeTrue()
        ->and($group->approvedMembershipOf($member)?->role())->toBe(MembershipRole::ADMIN);
});

it('rejects ownership transfer by someone who is not the owner', function () {
    $owner = MemberUserId::generate();
    $group = makeGroup($owner);
    $admin = MemberUserId::generate();

    $membership = $group->requestMembership($admin)->value();
    $group->approveMembership($membership->id(), $owner, 0);
    $group->promoteToAdmin($membership->id(), $owner);

    $transfer = $group->transferOwnership($admin, $admin);

    expect($transfer->isFailure())->toBeTrue()
        ->and($transfer->errors()[0]->code)->toBe('GROUP_OWNER_REQUIRED');
});

it('rejects ownership transfer to a non approved member', function () {
    $owner = MemberUserId::generate();
    $group = makeGroup($owner);
    $pending = $group->requestMembership(MemberUserId::generate())->value();

    $transfer = $group->transferOwnership($pending->userId(), $owner);

    expect($transfer->isFailure())->toBeTrue()
        ->and($transfer->errors()[0]->code)->toBe('GROUP_MEMBERSHIP_NOT_APPROVED');
});

it('treats ownership transfer to the current owner as a no-op', function () {
    $owner = MemberUserId::generate();
    $group = makeGroup($owner);

    $transfer = $group->transferOwnership($owner, $owner);

    expect($transfer->isSuccess())->toBeTrue()
        ->and($transfer->value())->toBeFalse();
});

it('allows ownership transfer on an archived group', function () {
    $owner = MemberUserId::generate();
    $group = makeGroup($owner);
    $member = MemberUserId::generate();

    $membership = $group->requestMembership($member)->value();
    $group->approveMembership($membership->id(), $owner, 0);
    $group->archive($owner, 'Pausa.', false, \App\Shared\Domain\ValueObject\Instant::now());

    $transfer = $group->transferOwnership($member, $owner);

    expect($transfer->isSuccess())->toBeTrue()
        ->and($group->ownerId()->equals($member))->toBeTrue();
});

it('only allows the owner to delete the group', function () {
    $owner = MemberUserId::generate();
    $group = makeGroup($owner);
    $admin = MemberUserId::generate();

    $membership = $group->requestMembership($admin)->value();
    $group->approveMembership($membership->id(), $owner, 0);
    $group->promoteToAdmin($membership->id(), $owner);

    $rejected = $group->delete($admin, null, \App\Shared\Domain\ValueObject\Instant::now());

    expect($rejected->isFailure())->toBeTrue()
        ->and($rejected->errors()[0]->code)->toBe('GROUP_OWNER_REQUIRED');

    $deleted = $group->delete($owner, null, \App\Shared\Domain\ValueObject\Instant::now());

    expect($deleted->isSuccess())->toBeTrue()
        ->and($group->isDeleted())->toBeTrue();
});
