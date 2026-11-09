<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

final readonly class ActivityCancelled extends AbstractDomainEvent
{
    private function __construct(
        private string $activityId,
        private string $groupId,
        private ?string $actorId,
        private ?string $reason,
        private bool $automatic,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $activityId,
        string $groupId,
        ?string $actorId,
        ?string $reason,
        bool $automatic = false,
    ): self {
        return new self($activityId, $groupId, $actorId, $reason, $automatic, Instant::now());
    }

    public function eventName(): string
    {
        return 'activity.cancelled';
    }

    public function userId(): ?string
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

    public function actorId(): ?string
    {
        return $this->actorId;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function automatic(): bool
    {
        return $this->automatic;
    }
}
