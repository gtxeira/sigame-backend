<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\RemoveMember;

use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MembershipId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;

final readonly class RemoveMemberCommand
{
    public function __construct(
        public GroupId $groupId,
        public MembershipId $membershipId,
        public MemberUserId $actorId,
        public string $reason,
    ) {
    }
}
