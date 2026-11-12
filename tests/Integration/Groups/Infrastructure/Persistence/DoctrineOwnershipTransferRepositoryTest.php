<?php

declare(strict_types=1);

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Entity\OwnershipTransfer;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Groups\Infrastructure\Persistence\Doctrine\Repository\DoctrineOwnershipTransferRepository;
use App\Shared\Domain\ValueObject\Modality;
use Doctrine\ORM\EntityManagerInterface;

it('records an ownership transfer in the append-only history', function () {
    $groups = $this->service(GroupRepositoryInterface::class);
    $transfers = $this->service(DoctrineOwnershipTransferRepository::class);
    $entityManager = $this->service(EntityManagerInterface::class);

    $owner = MemberUserId::generate();
    $newOwner = MemberUserId::generate();

    $group = Group::create(
        name: GroupName::fromString('Corrida no Parque')->value(),
        description: null,
        modality: Modality::RUNNING,
        communicationLink: null,
        createdBy: $owner,
    );

    $membership = $group->requestMembership($newOwner)->value();
    $group->approveMembership($membership->id(), $owner, 0);
    $groups->save($group);

    $transfer = OwnershipTransfer::record(
        group: $group,
        previousOwnerId: $owner,
        newOwnerId: $newOwner,
        actorId: $owner,
    );

    $transfers->record($transfer);
    $entityManager->clear();

    $found = $transfers->find($transfer->id()->value());
    assert($found instanceof OwnershipTransfer);

    expect($found->groupId()->equals($group->id()))->toBeTrue()
        ->and($found->previousOwnerId()->equals($owner))->toBeTrue()
        ->and($found->newOwnerId()->equals($newOwner))->toBeTrue()
        ->and($found->actorId()->equals($owner))->toBeTrue();
});
