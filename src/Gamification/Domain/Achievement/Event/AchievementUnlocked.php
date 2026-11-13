<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Achievement\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

final readonly class AchievementUnlocked extends AbstractDomainEvent
{
    private function __construct(
        private string $userIdValue,
        private string $codeValue,
        private string $nameValue,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $userId, string $code, string $name): self
    {
        return new self($userId, $code, $name, Instant::now());
    }

    public function eventName(): string
    {
        return 'gamification.achievement.unlocked';
    }

    public function userId(): string
    {
        return $this->userIdValue;
    }

    public function code(): string
    {
        return $this->codeValue;
    }

    public function name(): string
    {
        return $this->nameValue;
    }
}
