<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\StartActivity;

use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;

final readonly class StartActivityCommand
{
    public function __construct(
        public ActivityId $activityId,
        public ActivityUserId $actorId,
        public bool $isAdminSigame,
    ) {
    }
}
