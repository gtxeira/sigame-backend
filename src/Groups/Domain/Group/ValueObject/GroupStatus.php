<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\ValueObject;

/**
 * Estado do grupo, derivado da quantidade de membros aprovados e do limite
 * opcional configurado. Grupos ilimitados nunca alcançam `FULL`.
 */
enum GroupStatus: string
{
    case FORMING = 'forming';
    case ACTIVE = 'active';
    case FULL = 'full';

    public function canReceiveActivity(): bool
    {
        return self::FORMING !== $this;
    }
}
