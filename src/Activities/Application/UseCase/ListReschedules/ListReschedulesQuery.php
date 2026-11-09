<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\ListReschedules;

use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;

final readonly class ListReschedulesQuery
{
    public function __construct(
        public ActivityId $activityId,
        public ActivityUserId $viewerId,
        public bool $isAdminSigame,
    ) {
    }
}
