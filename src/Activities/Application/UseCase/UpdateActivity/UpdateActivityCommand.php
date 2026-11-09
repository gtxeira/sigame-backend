<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\UpdateActivity;

use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;

final readonly class UpdateActivityCommand
{
    public function __construct(
        public ActivityId $activityId,
        public ActivityUserId $actorId,
        public bool $isAdminSigame,
        public bool $titleProvided = false,
        public ?string $title = null,
        public bool $descriptionProvided = false,
        public ?string $description = null,
        public bool $meetingPointProvided = false,
        public ?string $meetingPoint = null,
        public bool $routeProvided = false,
        public ?string $route = null,
        public bool $distanceProvided = false,
        public ?int $estimatedDistanceMeters = null,
        public bool $startsAtProvided = false,
        public ?string $startsAt = null,
        public bool $durationProvided = false,
        public ?int $estimatedDurationMinutes = null,
        public ?string $reason = null,
    ) {
    }
}
