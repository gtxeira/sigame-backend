<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando um grupo é excluído logicamente.
 */
final readonly class GroupDeleted extends AbstractDomainEvent
{
    private function __construct(
        private string $groupId,
        private string $ownerId,
        private string $deletedBy,
        private ?string $reason,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $groupId,
        string $ownerId,
        string $deletedBy,
        ?string $reason,
    ): self {
        return new self($groupId, $ownerId, $deletedBy, $reason, Instant::now());
    }

    public function eventName(): string
    {
        return 'group.deleted';
    }

    public function userId(): string
    {
        return $this->deletedBy;
    }

    public function groupId(): string
    {
        return $this->groupId;
    }

    public function ownerId(): string
    {
        return $this->ownerId;
    }

    public function deletedBy(): string
    {
        return $this->deletedBy;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
