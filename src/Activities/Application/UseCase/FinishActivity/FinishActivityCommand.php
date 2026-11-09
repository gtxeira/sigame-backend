<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\FinishActivity;

use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;

final readonly class FinishActivityCommand
{
    public function __construct(
        public ActivityId $activityId,
        public ActivityUserId $actorId,
        public bool $isAdminSigame,
    ) {
    }
}
