<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\EnrollInActivity;

use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;

final readonly class EnrollInActivityCommand
{
    public function __construct(
        public ActivityId $activityId,
        public ActivityUserId $userId,
    ) {
    }
}
