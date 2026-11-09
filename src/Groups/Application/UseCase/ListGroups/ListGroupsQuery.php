<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ListGroups;

use App\Groups\Domain\Group\Repository\GroupSearchCriteria;

final readonly class ListGroupsQuery
{
    public function __construct(
        public GroupSearchCriteria $criteria,
    ) {
    }
}
