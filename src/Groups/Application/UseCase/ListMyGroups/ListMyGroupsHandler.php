<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ListMyGroups;

use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Lista os grupos em que o usuário possui participação aprovada.
 */
final readonly class ListMyGroupsHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * @return Result<list<Group>>
     */
    public function __invoke(ListMyGroupsQuery $query): Result
    {
        return Result::success(
            $this->groupRepository->findApprovedByMember($query->viewerId),
        );
    }
}
