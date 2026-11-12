<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\DeleteGroup;

use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Groups\Domain\Group\ValueObject\MemberUserId;

final readonly class DeleteGroupCommand
{
    public function __construct(
        public GroupId $groupId,
        public MemberUserId $actorId,
        public ?string $reason = null,
    ) {
    }
}
