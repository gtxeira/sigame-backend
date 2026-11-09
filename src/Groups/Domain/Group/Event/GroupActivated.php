<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando o grupo atinge o mínimo de membros aprovados.
 */
final readonly class GroupActivated extends AbstractDomainEvent
{
    private function __construct(
        private string $groupId,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $groupId): self
    {
        return new self($groupId, Instant::now());
    }

    public function userId(): ?string
    {
        return null;
    }

    public function eventName(): string
    {
        return 'group.activated';
    }

    public function groupId(): string
    {
        return $this->groupId;
    }
}
