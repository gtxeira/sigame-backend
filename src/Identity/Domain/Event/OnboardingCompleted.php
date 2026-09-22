<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\ValueObject\DateTime;

/**
 * Evento disparado quando o usuário completa o onboarding.
 */
final readonly class OnboardingCompleted implements DomainEventInterface
{
    public function __construct(
        private string $userId,
    ) {
    }

    public function eventName(): string
    {
        return 'onboarding.completed';
    }

    public function occurredAt(): DateTime
    {
        return DateTime::now();
    }

    public function userId(): string
    {
        return $this->userId;
    }
}
