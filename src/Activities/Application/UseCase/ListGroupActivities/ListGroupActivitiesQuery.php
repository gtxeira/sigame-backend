<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\ListGroupActivities;

use App\Activities\Domain\Activity\Repository\ActivitySearchCriteria;
use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;

final readonly class ListGroupActivitiesQuery
{
    public function __construct(
        public ActivityGroupId $groupId,
        public ActivityUserId $viewerId,
        public bool $isAdminSigame,
        public ActivitySearchCriteria $criteria,
    ) {
    }
}
