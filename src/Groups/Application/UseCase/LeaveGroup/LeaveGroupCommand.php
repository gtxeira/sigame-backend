<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\LeaveGroup;

use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;

final readonly class LeaveGroupCommand
{
    public function __construct(
        public GroupId $groupId,
        public MemberUserId $actorId,
    ) {
    }
}
