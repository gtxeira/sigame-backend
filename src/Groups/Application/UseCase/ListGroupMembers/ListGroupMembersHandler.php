<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ListGroupMembers;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\MembershipRole;
use App\Groups\Domain\Group\ValueObject\MembershipStatus;
use App\Shared\Domain\Functional\Result;

/**
 * Lista os membros aprovados de um grupo para quem já participa dele.
 *
 * A ordem é: responsável, supervisor, administradores e demais participantes.
 * Nomes e fotos são resolvidos pela porta `UserMemberProfileReaderInterface`.
 */
final readonly class ListGroupMembersHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * @return Result<list<Membership>>
     */
    public function __invoke(ListGroupMembersQuery $query): Result
    {
        $group = $this->groupRepository->findById($query->groupId);

        if (null === $group || $group->isDeleted()) {
            return Result::failure([GroupErrors::groupNotFound()]);
        }

        $viewerMembership = $group->membershipFor($query->viewerId);

        if (null === $viewerMembership
            || MembershipStatus::APPROVED->value !== $viewerMembership->status()->value
        ) {
            return Result::failure([GroupErrors::membersOnly()]);
        }

        $members = array_values(array_filter(
            $group->memberships(),
            static fn (Membership $membership): bool => MembershipStatus::APPROVED->value === $membership->status()->value,
        ));

        usort($members, static function (Membership $left, Membership $right) use ($group): int {
            return self::priority($left, $group) <=> self::priority($right, $group);
        });

        return Result::success($members);
    }

    private static function priority(Membership $membership, Group $group): int
    {
        if ($group->isOwnedBy($membership->userId())) {
            return 0;
        }

        if ($membership->isSupervisor()) {
            return 1;
        }

        return MembershipRole::ADMIN === $membership->role() ? 2 : 3;
    }
}
