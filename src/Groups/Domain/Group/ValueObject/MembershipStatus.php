<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\ValueObject;

/**
 * Situação de uma participação no grupo.
 *
 * Cada solicitação gera um episódio próprio; rejeição e remoção preservam o
 * histórico para auditoria.
 */
enum MembershipStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REMOVED = 'removed';

    public function isActive(): bool
    {
        return self::PENDING === $this || self::APPROVED === $this;
    }
}
