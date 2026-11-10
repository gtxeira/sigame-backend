<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado após a suspensão de uma conta.
 */
final readonly class UserSuspended extends AbstractDomainEvent
{
    private function __construct(
        private string $userIdValue,
        private string $suspensionId,
        private string $actorId,
        private string $reason,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $userId,
        string $suspensionId,
        string $actorId,
        string $reason,
    ): self {
        return new self($userId, $suspensionId, $actorId, $reason, Instant::now());
    }

    public function eventName(): string
    {
        return 'identity.user.suspended';
    }

    public function userId(): string
    {
        return $this->userIdValue;
    }

    public function suspensionId(): string
    {
        return $this->suspensionId;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
