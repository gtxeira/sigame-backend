<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

final readonly class ActivityInvalidated extends AbstractDomainEvent
{
    /**
     * @param list<string> $validatedUserIds
     */
    private function __construct(
        private string $activityId,
        private string $groupId,
        private string $actorId,
        private string $reason,
        private array $validatedUserIds,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    /**
     * @param list<string> $validatedUserIds
     */
    public static function now(
        string $activityId,
        string $groupId,
        string $actorId,
        string $reason,
        array $validatedUserIds,
    ): self {
        return new self($activityId, $groupId, $actorId, $reason, $validatedUserIds, Instant::now());
    }

    public function eventName(): string
    {
        return 'activity.invalidated';
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

    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * @return list<string>
     */
    public function validatedUserIds(): array
    {
        return $this->validatedUserIds;
    }
}
