<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\Admin\ListAdminGroups;

use App\Groups\Domain\Group\Repository\GroupSearchCriteria;

final readonly class ListAdminGroupsQuery
{
    public function __construct(
        public GroupSearchCriteria $criteria,
    ) {
    }
}
