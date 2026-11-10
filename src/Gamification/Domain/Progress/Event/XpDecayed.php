<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Progress\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

final readonly class XpDecayed extends AbstractDomainEvent
{
    private function __construct(
        private string $userIdValue,
        private int $amount,
        private int $balance,
        private int $level,
        private string $periodEnd,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $userId,
        int $amount,
        int $balance,
        int $level,
        string $periodEnd,
    ): self {
        return new self($userId, $amount, $balance, $level, $periodEnd, Instant::now());
    }

    public function eventName(): string
    {
        return 'gamification.xp.decayed';
    }

    public function userId(): string
    {
        return $this->userIdValue;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function balance(): int
    {
        return $this->balance;
    }

    public function level(): int
    {
        return $this->level;
    }

    public function periodEnd(): string
    {
        return $this->periodEnd;
    }
}
