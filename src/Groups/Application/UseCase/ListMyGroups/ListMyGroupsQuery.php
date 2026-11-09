<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ListMyGroups;

use App\Groups\Domain\Group\ValueObject\MemberUserId;

final readonly class ListMyGroupsQuery
{
    public function __construct(
        public MemberUserId $viewerId,
    ) {
    }
}
