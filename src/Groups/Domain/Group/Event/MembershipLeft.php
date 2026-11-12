<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando um membro aprovado sai do grupo por vontade própria.
 */
final readonly class MembershipLeft extends AbstractDomainEvent
{
    private function __construct(
        private string $groupId,
        private string $membershipId,
        private string $userId,
        private string $reason,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $groupId,
        string $membershipId,
        string $userId,
        string $reason,
    ): self {
        return new self($groupId, $membershipId, $userId, $reason, Instant::now());
    }

    public function eventName(): string
    {
        return 'group.membership.left';
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function groupId(): string
    {
        return $this->groupId;
    }

    public function membershipId(): string
    {
        return $this->membershipId;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
