<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Progress\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

final readonly class WeeklyGoalCompleted extends AbstractDomainEvent
{
    private function __construct(
        private string $userIdValue,
        private string $weekKey,
        private int $streak,
        private int $xp,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $userId, string $weekKey, int $streak, int $xp): self
    {
        return new self($userId, $weekKey, $streak, $xp, Instant::now());
    }

    public function eventName(): string
    {
        return 'gamification.weekly.goal.completed';
    }

    public function userId(): string
    {
        return $this->userIdValue;
    }

    public function weekKey(): string
    {
        return $this->weekKey;
    }

    public function streak(): int
    {
        return $this->streak;
    }

    public function xp(): int
    {
        return $this->xp;
    }
}
