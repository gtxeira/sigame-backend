<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ListMyMembershipRequests;

use App\Groups\Domain\Group\ValueObject\MemberUserId;

final readonly class ListMyMembershipRequestsQuery
{
    public function __construct(
        public MemberUserId $viewerId,
    ) {
    }
}
