<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\CancelActivity;

use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;

final readonly class CancelActivityCommand
{
    public function __construct(
        public ActivityId $activityId,
        public ActivityUserId $actorId,
        public bool $isAdminSigame,
        public ?string $reason = null,
    ) {
    }
}
