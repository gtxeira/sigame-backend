<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ListGroups;

use App\Groups\Domain\Group\Repository\GroupPage;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Lista grupos com filtros e paginação.
 */
final readonly class ListGroupsHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * @return Result<GroupPage>
     */
    public function __invoke(ListGroupsQuery $query): Result
    {
        return Result::success($this->groupRepository->search($query->criteria));
    }
}
