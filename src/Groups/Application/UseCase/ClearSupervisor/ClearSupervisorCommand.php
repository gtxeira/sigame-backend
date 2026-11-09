<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ClearSupervisor;

use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;

final readonly class ClearSupervisorCommand
{
    public function __construct(
        public GroupId $groupId,
        public MemberUserId $actorId,
    ) {
    }
}
