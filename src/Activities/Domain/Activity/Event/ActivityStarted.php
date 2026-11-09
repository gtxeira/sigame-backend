<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

final readonly class ActivityStarted extends AbstractDomainEvent
{
    private function __construct(
        private string $activityId,
        private string $groupId,
        private string $actorId,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $activityId, string $groupId, string $actorId): self
    {
        return new self($activityId, $groupId, $actorId, Instant::now());
    }

    public function eventName(): string
    {
        return 'activity.started';
    }

    public function userId(): string
    {
        return $this->actorId;
    }

    public function activityId(): string
    {
        return $this->activityId;
    }

    public function groupId(): string
    {
        return $this->groupId;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }
}
