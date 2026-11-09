<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando um membro é removido do grupo.
 */
final readonly class MemberRemoved extends AbstractDomainEvent
{
    private function __construct(
        private string $groupId,
        private string $membershipId,
        private string $userId,
        private string $removedBy,
        private string $reason,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $groupId,
        string $membershipId,
        string $userId,
        string $removedBy,
        string $reason,
    ): self {
        return new self($groupId, $membershipId, $userId, $removedBy, $reason, Instant::now());
    }

    public function eventName(): string
    {
        return 'group.member.removed';
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

    public function removedBy(): string
    {
        return $this->removedBy;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
