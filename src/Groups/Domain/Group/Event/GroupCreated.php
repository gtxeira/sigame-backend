<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando um grupo é criado.
 */
final readonly class GroupCreated extends AbstractDomainEvent
{
    private function __construct(
        private string $groupId,
        private string $createdBy,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $groupId, string $createdBy): self
    {
        return new self($groupId, $createdBy, Instant::now());
    }

    public function userId(): string
    {
        return $this->createdBy;
    }

    public function eventName(): string
    {
        return 'group.created';
    }

    public function groupId(): string
    {
        return $this->groupId;
    }

    public function createdBy(): string
    {
        return $this->createdBy;
    }
}
