<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando a responsabilidade pelo grupo muda de dono.
 */
final readonly class GroupOwnershipTransferred extends AbstractDomainEvent
{
    private function __construct(
        private string $groupId,
        private string $previousOwnerId,
        private string $newOwnerId,
        private string $actorId,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $groupId,
        string $previousOwnerId,
        string $newOwnerId,
        string $actorId,
    ): self {
        return new self($groupId, $previousOwnerId, $newOwnerId, $actorId, Instant::now());
    }

    public function eventName(): string
    {
        return 'group.ownership.transferred';
    }

    public function userId(): string
    {
        return $this->actorId;
    }

    public function groupId(): string
    {
        return $this->groupId;
    }

    public function previousOwnerId(): string
    {
        return $this->previousOwnerId;
    }

    public function newOwnerId(): string
    {
        return $this->newOwnerId;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }
}
