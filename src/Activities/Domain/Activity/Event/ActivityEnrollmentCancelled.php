<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

final readonly class ActivityEnrollmentCancelled extends AbstractDomainEvent
{
    private function __construct(
        private string $activityId,
        private string $groupId,
        private string $userIdValue,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $activityId, string $groupId, string $userId): self
    {
        return new self($activityId, $groupId, $userId, Instant::now());
    }

    public function eventName(): string
    {
        return 'activity.enrollment.cancelled';
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
}
