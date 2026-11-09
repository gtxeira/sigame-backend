<?php

declare(strict_types=1);

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\ValueObject\GroupDescription;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Groups\Domain\Group\ValueObject\MembershipRole;
use App\Groups\Domain\Group\ValueObject\MembershipStatus;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Domain\Group\ValueObject\RemovalReason;
use App\Shared\Domain\ValueObject\Modality;

function makeGroup(MemberUserId $admin): Group
{
    return Group::create(
        name: GroupName::fromString('Corrida no Parque')->value(),
        description: GroupDescription::fromString('Encontros aos sábados.')->value(),
        modality: Modality::RUNNING,
        communicationLink: null,
        createdBy: $admin,
    );
}

it('creates a group with the creator as the first admin', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin);

    $adminMembership = $group->approvedMembershipOf($admin);

    expect($group->createdBy()->equals($admin))->toBeTrue()
        ->and($group->status())->toBe(GroupStatus::FORMING)
        ->and($group->approvedCount())->toBe(1)
        ->and($group->approvedAdminCount())->toBe(1)
        ->and($adminMembership?->role())->toBe(MembershipRole::ADMIN)
        ->and($adminMembership?->status())->toBe(MembershipStatus::APPROVED);
});

it('activates the group at three approved members and becomes full at five', function () {
    $admin = MemberUserId::generate();
    $group = makeGroup($admin);

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

it('supports multiple admins but never removes the last one', function () {
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
        ->and($rejected->errors()[0]->code)->toBe('GROUP_LAST_ADMIN_REQUIRED');
});

it('does not remove the last admin', function () {
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
        ->and($result->errors()[0]->code)->toBe('GROUP_LAST_ADMIN_REQUIRED');
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
