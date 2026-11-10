<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Progress\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

final readonly class LevelLost extends AbstractDomainEvent
{
    private function __construct(
        private string $userIdValue,
        private int $previousLevel,
        private int $level,
        private string $reason,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $userId,
        int $previousLevel,
        int $level,
        string $reason,
    ): self {
        return new self($userId, $previousLevel, $level, $reason, Instant::now());
    }

    public function eventName(): string
    {
        return 'gamification.level.lost';
    }

    public function userId(): string
    {
        return $this->userIdValue;
    }

    public function previousLevel(): int
    {
        return $this->previousLevel;
    }

    public function level(): int
    {
        return $this->level;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
