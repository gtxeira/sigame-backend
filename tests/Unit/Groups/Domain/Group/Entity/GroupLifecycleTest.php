<?php

declare(strict_types=1);

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Policy\GroupPolicy;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Domain\Group\ValueObject\RemovalReason;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Domain\ValueObject\Modality;

function lifecycleGroup(MemberUserId $admin): Group
{
    return Group::create(
        name: GroupName::fromString('Corrida no Parque')->value(),
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
        createdBy: $admin,
    );
}

/**
 * @return list<\App\Groups\Domain\Group\Entity\Membership>
 */
function lifecycleApproveMerged(Group $group, MemberUserId $admin, int $count): array
{
    $approved = [];

    for ($index = 0; $index < $count; ++$index) {
        $membership = $group->requestMembership(MemberUserId::generate())->value();
        $group->approveMembership($membership->id(), $admin, 0);
        $approved[] = $membership;
    }

    return $approved;
}

it('persists the activation instant when the group reaches three approved members', function () {
    $admin = MemberUserId::generate();
    $group = lifecycleGroup($admin);

    expect($group->activatedAt())->toBeNull();

    lifecycleApproveMerged($group, $admin, 2);

    expect($group->approvedCount())->toBe(3)
        ->and($group->activatedAt())->not->toBeNull();
});

it('clears the activation instant when the group drops below three approved members', function () {
    $admin = MemberUserId::generate();
    $group = lifecycleGroup($admin);

    $members = lifecycleApproveMerged($group, $admin, 2);

    expect($group->activatedAt())->not->toBeNull();

    $removal = $group->removeMembership(
        membershipId: $members[0]->id(),
        actor: $admin,
        reason: RemovalReason::fromString('Motivo da remoção')->value(),
    );

    expect($removal->isSuccess())->toBeTrue()
        ->and($group->approvedCount())->toBe(2)
        ->and($group->activatedAt())->toBeNull();
});

it('withdraws a pending request when the user leaves', function () {
    $admin = MemberUserId::generate();
    $member = MemberUserId::generate();
    $group = lifecycleGroup($admin);

    $membership = $group->requestMembership($member)->value();

    $leave = $group->leave($member, RemovalReason::voluntaryExit());

    expect($leave->isSuccess())->toBeTrue()
        ->and($membership->status()->value)->toBe('removed')
        ->and($membership->removedBy()?->equals($member))->toBeTrue()
        ->and($membership->removalReason()?->value())->toBe('Solicitação cancelada pelo usuário.');
});

it('removes an approved member when the user leaves', function () {
    $admin = MemberUserId::generate();
    $member = MemberUserId::generate();
    $group = lifecycleGroup($admin);

    $membership = $group->requestMembership($member)->value();
    $group->approveMembership($membership->id(), $admin, 0);

    $leave = $group->leave($member, RemovalReason::voluntaryExit());

    expect($leave->isSuccess())->toBeTrue()
        ->and($membership->status()->value)->toBe('removed')
        ->and($membership->removalReason()?->value())->toBe('Saída voluntária.');
});

it('prevents the last admin from leaving the group', function () {
    $admin = MemberUserId::generate();
    $group = lifecycleGroup($admin);

    $leave = $group->leave($admin, RemovalReason::voluntaryExit());

    expect($leave->isFailure())->toBeTrue()
        ->and($leave->errors()[0]->code)->toBe('GROUP_LAST_ADMIN_REQUIRED');
});

it('archives a group and blocks management operations', function () {
    $admin = MemberUserId::generate();
    $group = lifecycleGroup($admin);

    $archive = $group->archive(
        actor: $admin,
        reason: 'Pausa nas atividades.',
        automatic: false,
        at: Instant::now(),
    );

    expect($archive->isSuccess())->toBeTrue()
        ->and($archive->value())->toBeTrue()
        ->and($group->isArchived())->toBeTrue()
        ->and($group->archiveReason())->toBe('Pausa nas atividades.');

    $request = $group->requestMembership(MemberUserId::generate());
    $update = $group->changeDetails(
        name: GroupName::fromString('Outro nome')->value(),
        description: null,
        modality: Modality::CYCLING,
        communicationLink: null,
        maxMembers: null,
        actor: $admin,
    );

    expect($request->isFailure())->toBeTrue()
        ->and($request->errors()[0]->code)->toBe('GROUP_ARCHIVED')
        ->and($update->isFailure())->toBeTrue()
        ->and($update->errors()[0]->code)->toBe('GROUP_ARCHIVED');
});

it('archives automatically without an actor and is idempotent', function () {
    $group = lifecycleGroup(MemberUserId::generate());

    $first = $group->archive(
        actor: null,
        reason: 'Arquivado automaticamente.',
        automatic: true,
        at: Instant::now(),
    );
    $second = $group->archive(
        actor: null,
        reason: 'Arquivado automaticamente.',
        automatic: true,
        at: Instant::now(),
    );

    expect($first->isSuccess())->toBeTrue()
        ->and($first->value())->toBeTrue()
        ->and($second->isSuccess())->toBeTrue()
        ->and($second->value())->toBeFalse()
        ->and($group->archivedBy())->toBeNull();
});

it('unarchives a group only for an approved admin', function () {
    $admin = MemberUserId::generate();
    $group = lifecycleGroup($admin);

    $group->archive($admin, null, false, Instant::now());

    $nonAdmin = $group->unarchive(MemberUserId::generate());
    $unarchive = $group->unarchive($admin);

    expect($nonAdmin->isFailure())->toBeTrue()
        ->and($nonAdmin->errors()[0]->code)->toBe('GROUP_ADMIN_REQUIRED')
        ->and($unarchive->isSuccess())->toBeTrue()
        ->and($unarchive->value())->toBeTrue()
        ->and($group->isArchived())->toBeFalse();
});

it('deletes a group logically, preserving memberships and blocking operations', function () {
    $admin = MemberUserId::generate();
    $member = MemberUserId::generate();
    $group = lifecycleGroup($admin);

    $membership = $group->requestMembership($member)->value();
    $group->approveMembership($membership->id(), $admin, 0);

    $delete = $group->delete($admin, 'Grupo encerrado.', Instant::now());

    expect($delete->isSuccess())->toBeTrue()
        ->and($delete->value())->toBeTrue()
        ->and($group->isDeleted())->toBeTrue()
        ->and($group->deleteReason())->toBe('Grupo encerrado.')
        ->and($group->memberships())->toHaveCount(2);

    $request = $group->requestMembership(MemberUserId::generate());
    $secondDelete = $group->delete($admin, null, Instant::now());

    expect($request->isFailure())->toBeTrue()
        ->and($request->errors()[0]->code)->toBe('GROUP_NOT_FOUND')
        ->and($secondDelete->isFailure())->toBeTrue()
        ->and($secondDelete->errors()[0]->code)->toBe('GROUP_NOT_FOUND');
});

it('keeps the policy constants aligned with the lifecycle rules', function () {
    expect(GroupPolicy::MAX_PARTICIPATION_PER_USER)->toBe(5)
        ->and(GroupPolicy::MAX_FORMING_PER_CREATOR)->toBe(1)
        ->and(GroupPolicy::MIN_LEVEL_TO_JOIN)->toBe(2)
        ->and(GroupPolicy::FORMING_IDLE_DAYS)->toBe(30);
});
