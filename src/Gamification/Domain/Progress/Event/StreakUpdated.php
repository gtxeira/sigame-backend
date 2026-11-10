<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Progress\Event;

use App\Gamification\Domain\Streak\ValueObject\WeeklyStreakState;
use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

final readonly class StreakUpdated extends AbstractDomainEvent
{
    private function __construct(
        private string $userIdValue,
        private int $current,
        private int $best,
        private WeeklyStreakState $state,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $userId,
        int $current,
        int $best,
        WeeklyStreakState $state,
    ): self {
        return new self($userId, $current, $best, $state, Instant::now());
    }

    public function eventName(): string
    {
        return 'gamification.streak.updated';
    }

    public function userId(): string
    {
        return $this->userIdValue;
    }

    public function current(): int
    {
        return $this->current;
    }

    public function best(): int
    {
        return $this->best;
    }

    public function state(): WeeklyStreakState
    {
        return $this->state;
    }
}
