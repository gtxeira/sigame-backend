<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\DesignateSupervisor;

use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MembershipId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;

final readonly class DesignateSupervisorCommand
{
    public function __construct(
        public GroupId $groupId,
        public MembershipId $membershipId,
        public MemberUserId $actorId,
    ) {
    }
}
