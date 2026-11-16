<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\Admin\ListAdminGroups;

use App\Groups\Domain\Group\Repository\GroupPage;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Lista grupos para a administração SIGAME, incluindo arquivados e excluídos.
 */
final readonly class ListAdminGroupsHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * @return Result<GroupPage>
     */
    public function __invoke(ListAdminGroupsQuery $query): Result
    {
        return Result::success($this->groupRepository->search($query->criteria));
    }
}
