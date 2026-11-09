<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\ListMyActivities;

use App\Activities\Domain\Activity\Repository\ActivitySearchCriteria;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;

final readonly class ListMyActivitiesQuery
{
    public function __construct(
        public ActivityUserId $userId,
        public ActivitySearchCriteria $criteria,
    ) {
    }
}
