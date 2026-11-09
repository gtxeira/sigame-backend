<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\CreateActivity;

use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;

final readonly class CreateActivityCommand
{
    public function __construct(
        public ActivityGroupId $groupId,
        public ActivityUserId $actorId,
        public bool $isAdminSigame,
        public string $title,
        public ?string $description,
        public string $startsAt,
        public ?int $estimatedDurationMinutes,
        public string $meetingPoint,
        public string $route,
        public int $estimatedDistanceMeters,
    ) {
    }
}
