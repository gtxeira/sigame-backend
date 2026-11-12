<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando um grupo é arquivado.
 */
final readonly class GroupArchived extends AbstractDomainEvent
{
    private function __construct(
        private string $groupId,
        private string $createdBy,
        private ?string $archivedBy,
        private ?string $reason,
        private bool $automatic,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $groupId,
        string $createdBy,
        ?string $archivedBy,
        ?string $reason,
        bool $automatic,
    ): self {
        return new self($groupId, $createdBy, $archivedBy, $reason, $automatic, Instant::now());
    }

    public function eventName(): string
    {
        return 'group.archived';
    }

    public function userId(): ?string
    {
        return $this->archivedBy;
    }

    public function groupId(): string
    {
        return $this->groupId;
    }

    public function createdBy(): string
    {
        return $this->createdBy;
    }

    public function archivedBy(): ?string
    {
        return $this->archivedBy;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function automatic(): bool
    {
        return $this->automatic;
    }
}
