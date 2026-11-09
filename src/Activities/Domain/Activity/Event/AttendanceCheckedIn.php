<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

final readonly class AttendanceCheckedIn extends AbstractDomainEvent
{
    private function __construct(
        private string $activityId,
        private string $groupId,
        private string $attendanceId,
        private string $userIdValue,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $activityId,
        string $groupId,
        string $attendanceId,
        string $userId,
    ): self {
        return new self($activityId, $groupId, $attendanceId, $userId, Instant::now());
    }

    public function eventName(): string
    {
        return 'activity.attendance.checked_in';
    }

    public function userId(): string
    {
        return $this->userIdValue;
    }

    public function activityId(): string
    {
        return $this->activityId;
    }

    public function groupId(): string
    {
        return $this->groupId;
    }

    public function attendanceId(): string
    {
        return $this->attendanceId;
    }
}
