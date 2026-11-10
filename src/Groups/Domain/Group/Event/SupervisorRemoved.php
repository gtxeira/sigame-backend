<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando a designação de supervisor é removida.
 */
final readonly class SupervisorRemoved extends AbstractDomainEvent
{
    private function __construct(
        private string $groupId,
        private string $supervisorId,
        private string $actorId,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $groupId, string $supervisorId, string $actorId): self
    {
        return new self($groupId, $supervisorId, $actorId, Instant::now());
    }

    public function userId(): string
    {
        return $this->actorId;
    }

    public function eventName(): string
    {
        return 'group.supervisor.removed';
    }

    public function groupId(): string
    {
        return $this->groupId;
    }

    public function supervisorId(): string
    {
        return $this->supervisorId;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }
}
