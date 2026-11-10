<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Progress\Event;

use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

final readonly class XpAwarded extends AbstractDomainEvent
{
    private function __construct(
        private string $userIdValue,
        private int $amount,
        private XpKind $kind,
        private int $balance,
        private int $level,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $userId,
        int $amount,
        XpKind $kind,
        int $balance,
        int $level,
    ): self {
        return new self($userId, $amount, $kind, $balance, $level, Instant::now());
    }

    public function eventName(): string
    {
        return 'gamification.xp.awarded';
    }

    public function userId(): string
    {
        return $this->userIdValue;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function kind(): XpKind
    {
        return $this->kind;
    }

    public function balance(): int
    {
        return $this->balance;
    }

    public function level(): int
    {
        return $this->level;
    }
}
