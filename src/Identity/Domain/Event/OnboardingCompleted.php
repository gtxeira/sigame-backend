<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando o usuário completa o onboarding.
 */
final readonly class OnboardingCompleted extends AbstractDomainEvent
{
    private function __construct(
        private string $userId,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $userId): self
    {
        return new self(
            userId: $userId,
            occurredAt: Instant::now(),
        );
    }

    public function eventName(): string
    {
        return 'onboarding.completed';
    }

    public function userId(): string
    {
        return $this->userId;
    }
}
