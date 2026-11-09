<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Evento disparado quando uma solicitação de entrada é aprovada.
 */
final readonly class MembershipApproved extends AbstractDomainEvent
{
    private function __construct(
        private string $groupId,
        private string $membershipId,
        private string $userId,
        private string $decidedBy,
        Instant $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }

    public static function now(
        string $groupId,
        string $membershipId,
        string $userId,
        string $decidedBy,
    ): self {
        return new self($groupId, $membershipId, $userId, $decidedBy, Instant::now());
    }

    public function eventName(): string
    {
        return 'group.membership.approved';
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

    public function decidedBy(): string
    {
        return $this->decidedBy;
    }
}
