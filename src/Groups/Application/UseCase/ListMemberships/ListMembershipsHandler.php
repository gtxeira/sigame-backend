<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ListMemberships;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Lista as participações de um grupo para um administrador.
 */
final readonly class ListMembershipsHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * @return Result<list<Membership>>
     */
    public function __invoke(ListMembershipsQuery $query): Result
    {
        $group = $this->groupRepository->findById($query->groupId);

        if (null === $group) {
            return Result::failure([GroupErrors::groupNotFound()]);
        }

        if (!$group->canBeManagedBy($query->viewerId)) {
            return Result::failure([GroupErrors::adminRequired()]);
        }

        return Result::success($group->memberships());
    }
}
