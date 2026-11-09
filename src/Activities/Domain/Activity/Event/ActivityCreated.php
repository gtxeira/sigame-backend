<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

final readonly class ActivityCreated extends AbstractDomainEvent
{
    private function __construct(
        private string $activityId,
        private string $groupId,
        private string $createdBy,
        private Instant $startsAt,
        private Instant $endsAt,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $activityId,
        string $groupId,
        string $createdBy,
        Instant $startsAt,
        Instant $endsAt,
    ): self {
        return new self($activityId, $groupId, $createdBy, $startsAt, $endsAt, Instant::now());
    }

    public function eventName(): string
    {
        return 'activity.created';
    }

    public function userId(): string
    {
        return $this->createdBy;
    }

    public function activityId(): string
    {
        return $this->activityId;
    }

    public function groupId(): string
    {
        return $this->groupId;
    }

    public function createdBy(): string
    {
        return $this->createdBy;
    }

    public function startsAt(): Instant
    {
        return $this->startsAt;
    }

    public function endsAt(): Instant
    {
        return $this->endsAt;
    }
}
