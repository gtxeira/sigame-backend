<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando o próprio solicitante cancela uma solicitação pendente.
 */
final readonly class MembershipRequestWithdrawn extends AbstractDomainEvent
{
    private function __construct(
        private string $groupId,
        private string $membershipId,
        private string $userId,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(string $groupId, string $membershipId, string $userId): self
    {
        return new self($groupId, $membershipId, $userId, Instant::now());
    }

    public function eventName(): string
    {
        return 'group.membership.withdrawn';
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
}
