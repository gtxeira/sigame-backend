<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando um grupo arquivado é reativado.
 */
final readonly class GroupUnarchived extends AbstractDomainEvent
{
    private function __construct(
        private string $groupId,
        private string $actorId,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $groupId, string $actorId): self
    {
        return new self($groupId, $actorId, Instant::now());
    }

    public function eventName(): string
    {
        return 'group.unarchived';
    }

    public function userId(): string
    {
        return $this->actorId;
    }

    public function groupId(): string
    {
        return $this->groupId;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }
}
