<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\Admin\ListAdminActivities;

use App\Activities\Domain\Activity\Repository\ActivitySearchCriteria;

final readonly class ListAdminActivitiesQuery
{
    public function __construct(
        public ActivitySearchCriteria $criteria,
    ) {
    }
}
