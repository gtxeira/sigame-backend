<?php

declare(strict_types=1);

namespace App\Groups\Infrastructure\Group;

use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Application\Group\GroupAccessSnapshot;
use App\Shared\Domain\Exception\InvalidUuidException;

/**
 * Adapter Doctrine da porta de acesso a grupos.
 *
 * Activities importa apenas a interface em Shared; esta implementação
 * permanece dentro de Groups, dono do agregado.
 */
final readonly class DoctrineGroupAccessReader implements GroupAccessReaderInterface
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    public function snapshot(string $groupId, string $userId): ?GroupAccessSnapshot
    {
        try {
            $group = $this->groupRepository->findById(GroupId::fromString($groupId));
            $memberId = MemberUserId::fromString($userId);
        } catch (InvalidUuidException) {
            return null;
        }

        if (null === $group) {
            return null;
        }

        $membership = $group->approvedMembershipOf($memberId);

        return new GroupAccessSnapshot(
            acceptsActivities: $group->isActive(),
            isApprovedMember: null !== $membership,
            isAdmin: null !== $membership && $membership->isAdmin(),
            isSupervisor: null !== $membership && $membership->isSupervisor(),
        );
    }

    public function approvedGroupIds(string $userId): array
    {
        try {
            $memberId = MemberUserId::fromString($userId);
        } catch (InvalidUuidException) {
            return [];
        }

        return array_map(
            static fn ($group): string => $group->id()->value(),
            $this->groupRepository->findApprovedByMember($memberId),
        );
    }
}
