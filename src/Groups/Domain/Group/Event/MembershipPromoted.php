<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando um membro é promovido a administrador.
 */
final readonly class MembershipPromoted extends AbstractDomainEvent
{
    private function __construct(
        private string $groupId,
        private string $membershipId,
        private string $userId,
        private string $actorId,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $groupId,
        string $membershipId,
        string $userId,
        string $actorId,
    ): self {
        return new self($groupId, $membershipId, $userId, $actorId, Instant::now());
    }

    public function eventName(): string
    {
        return 'group.membership.promoted';
    }

    public function groupId(): string
    {
        return $this->groupId;
    }

    public function membershipId(): string
    {
        return $this->membershipId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function actorId(): string
    {
        return $this->actorId;
    }
}
