<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\ValidateAttendance;

use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Domain\Activity\ValueObject\AttendanceId;

final readonly class ValidateAttendanceCommand
{
    public function __construct(
        public ActivityId $activityId,
        public AttendanceId $attendanceId,
        public ActivityUserId $actorId,
        public bool $isAdminSigame,
    ) {
    }
}
