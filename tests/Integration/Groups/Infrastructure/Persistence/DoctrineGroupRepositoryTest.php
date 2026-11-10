<?php

declare(strict_types=1);

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\Repository\GroupSearchCriteria;
use App\Groups\Domain\Group\ValueObject\GroupMemberLimit;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Domain\ValueObject\Modality;
use Doctrine\ORM\EntityManagerInterface;

function makePersistedGroup(
    GroupRepositoryInterface $repository,
    MemberUserId $admin,
    string $name = 'Corrida no Parque',
    Modality $modality = Modality::RUNNING,
    ?int $maxMembers = null,
): Group {
    $group = Group::create(
        name: GroupName::fromString($name)->value(),
        description: null,
        modality: $modality,
        communicationLink: null,
        createdBy: $admin,
        maxMembers: null === $maxMembers ? null : GroupMemberLimit::fromInt($maxMembers)->value(),
    );

    $repository->save($group);

    return $group;
}

function makePersistedGroupWithMembers(
    GroupRepositoryInterface $repository,
    MemberUserId $admin,
    int $approvedMembers,
    string $name = 'Corrida no Parque',
    Modality $modality = Modality::RUNNING,
    ?int $maxMembers = null,
): Group {
    $group = makePersistedGroup($repository, $admin, $name, $modality, $maxMembers);

    for ($index = 1; $index < $approvedMembers; ++$index) {
        $membership = $group->requestMembership(MemberUserId::generate())->value();
        $group->approveMembership($membership->id(), $admin, 0);
    }

    $repository->save($group);

    return $group;
}

it('persists and reloads a group with its memberships', function () {
    $repository = $this->service(GroupRepositoryInterface::class);
    $entityManager = $this->service(EntityManagerInterface::class);
    $admin = MemberUserId::generate();

    $group = makePersistedGroup($repository, $admin);
    $membership = $group->requestMembership(MemberUserId::generate())->value();
    $group->approveMembership($membership->id(), $admin, 0);
    $repository->save($group);

    $entityManager->clear();

    $found = $repository->findById($group->id());
    assert($found instanceof Group);

    expect($found->createdBy()->equals($admin))->toBeTrue()
        ->and($found->name()->value())->toBe('Corrida no Parque')
        ->and($found->modality())->toBe(Modality::RUNNING)
        ->and($found->maxMembers())->toBeNull()
        ->and($found->approvedCount())->toBe(2)
        ->and($found->status())->toBe(GroupStatus::FORMING)
        ->and($found->memberships())->toHaveCount(2);
});

it('persists and reloads a configured member limit', function () {
    $repository = $this->service(GroupRepositoryInterface::class);
    $entityManager = $this->service(EntityManagerInterface::class);

    $group = makePersistedGroup($repository, MemberUserId::generate(), maxMembers: 8);
    $entityManager->clear();

    $found = $repository->findById($group->id());
    assert($found instanceof Group);

    expect($found->maxMembers()?->value())->toBe(8);
});

it('counts approved memberships per user', function () {
    $repository = $this->service(GroupRepositoryInterface::class);
    $member = MemberUserId::generate();

    $first = makePersistedGroup($repository, MemberUserId::generate(), 'Grupo 1');
    $second = makePersistedGroup($repository, MemberUserId::generate(), 'Grupo 2');

    foreach ([$first, $second] as $group) {
        $membership = $group->requestMembership($member)->value();
        $group->approveMembership($membership->id(), $group->createdBy(), 0);
        $repository->save($group);
    }

    expect($repository->countApprovedMemberships($member))->toBe(2);
});

it('finds groups approved for a member', function () {
    $repository = $this->service(GroupRepositoryInterface::class);
    $member = MemberUserId::generate();

    $group = makePersistedGroup($repository, MemberUserId::generate());
    $membership = $group->requestMembership($member)->value();
    $group->approveMembership($membership->id(), $group->createdBy(), 0);
    $repository->save($group);

    $groups = $repository->findApprovedByMember($member);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->id()->equals($group->id()))->toBeTrue();
});

it('filters groups by modality, status and name', function () {
    $repository = $this->service(GroupRepositoryInterface::class);

    $running = makePersistedGroup($repository, MemberUserId::generate(), 'Corrida no Parque', Modality::RUNNING);
    makePersistedGroup($repository, MemberUserId::generate(), 'Pedal da Serra', Modality::CYCLING);

    $byModality = $repository->search(new GroupSearchCriteria(modality: Modality::RUNNING));
    $byName = $repository->search(new GroupSearchCriteria(name: 'serra'));
    $byStatus = $repository->search(new GroupSearchCriteria(status: GroupStatus::FORMING));

    expect($byModality->total)->toBe(1)
        ->and($byModality->items[0]->id()->equals($running->id()))->toBeTrue()
        ->and($byName->total)->toBe(1)
        ->and($byName->items[0]->name()->value())->toBe('Pedal da Serra')
        ->and($byStatus->total)->toBe(2);
});

it('filters groups by derived status', function () {
    $repository = $this->service(GroupRepositoryInterface::class);

    $forming = makePersistedGroupWithMembers($repository, MemberUserId::generate(), 2, 'Em formação');
    $active = makePersistedGroupWithMembers($repository, MemberUserId::generate(), 3, 'Ativo');
    $full = makePersistedGroupWithMembers($repository, MemberUserId::generate(), 5, 'Cheio', maxMembers: 5);

    $formingPage = $repository->search(new GroupSearchCriteria(status: GroupStatus::FORMING));
    $activePage = $repository->search(new GroupSearchCriteria(status: GroupStatus::ACTIVE));
    $fullPage = $repository->search(new GroupSearchCriteria(status: GroupStatus::FULL));

    expect($formingPage->total)->toBe(1)
        ->and($formingPage->items[0]->id()->equals($forming->id()))->toBeTrue()
        ->and($activePage->total)->toBe(1)
        ->and($activePage->items[0]->id()->equals($active->id()))->toBeTrue()
        ->and($fullPage->total)->toBe(1)
        ->and($fullPage->items[0]->id()->equals($full->id()))->toBeTrue();
});

it('keeps unlimited groups active and available beyond five members', function () {
    $repository = $this->service(GroupRepositoryInterface::class);

    $unlimited = makePersistedGroupWithMembers($repository, MemberUserId::generate(), 6, 'Ilimitado');
    $full = makePersistedGroupWithMembers($repository, MemberUserId::generate(), 5, 'Cheio', maxMembers: 5);

    $activePage = $repository->search(new GroupSearchCriteria(status: GroupStatus::ACTIVE));
    $fullPage = $repository->search(new GroupSearchCriteria(status: GroupStatus::FULL));
    $availablePage = $repository->search(new GroupSearchCriteria(availableOnly: true));

    expect($activePage->total)->toBe(1)
        ->and($activePage->items[0]->id()->equals($unlimited->id()))->toBeTrue()
        ->and($fullPage->total)->toBe(1)
        ->and($fullPage->items[0]->id()->equals($full->id()))->toBeTrue()
        ->and($availablePage->total)->toBe(1)
        ->and($availablePage->items[0]->id()->equals($unlimited->id()))->toBeTrue();
});

it('combines status and availability filters', function () {
    $repository = $this->service(GroupRepositoryInterface::class);

    $forming = makePersistedGroupWithMembers($repository, MemberUserId::generate(), 2, 'Em formação');
    makePersistedGroupWithMembers($repository, MemberUserId::generate(), 3, 'Ativo');
    makePersistedGroupWithMembers($repository, MemberUserId::generate(), 5, 'Cheio', maxMembers: 5);

    $availableForming = $repository->search(new GroupSearchCriteria(
        status: GroupStatus::FORMING,
        availableOnly: true,
    ));
    $availableFull = $repository->search(new GroupSearchCriteria(
        status: GroupStatus::FULL,
        availableOnly: true,
    ));
    $available = $repository->search(new GroupSearchCriteria(availableOnly: true));

    expect($availableForming->total)->toBe(1)
        ->and($availableForming->items[0]->id()->equals($forming->id()))->toBeTrue()
        ->and($availableFull->total)->toBe(0)
        ->and($availableFull->items)->toBe([])
        ->and($available->total)->toBe(2);
});

it('paginates filtered groups keeping the filtered total', function () {
    $repository = $this->service(GroupRepositoryInterface::class);

    makePersistedGroupWithMembers($repository, MemberUserId::generate(), 2, 'Formação 1');
    makePersistedGroupWithMembers($repository, MemberUserId::generate(), 2, 'Formação 2');
    makePersistedGroupWithMembers($repository, MemberUserId::generate(), 3, 'Ativo');

    $page = $repository->search(new GroupSearchCriteria(
        status: GroupStatus::FORMING,
        page: 2,
        perPage: 1,
    ));

    expect($page->total)->toBe(2)
        ->and($page->items)->toHaveCount(1);
});
