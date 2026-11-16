<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\Admin\GetAdminGroup;

use App\Groups\Domain\Group\ValueObject\GroupId;

final readonly class GetAdminGroupQuery
{
    public function __construct(
        public GroupId $groupId,
    ) {
    }
}
