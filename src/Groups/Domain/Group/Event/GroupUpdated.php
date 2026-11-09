<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando os dados do grupo são atualizados.
 */
final readonly class GroupUpdated extends AbstractDomainEvent
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

    public function userId(): string
    {
        return $this->actorId;
    }

    public function eventName(): string
    {
        return 'group.updated';
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
