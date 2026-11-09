<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\ValueObject;

/**
 * Papel de uma participação aprovada no grupo.
 *
 * O grupo pode ter mais de um administrador; `createdBy` é independente deste
 * papel.
 */
enum MembershipRole: string
{
    case ADMIN = 'admin';
    case MEMBER = 'member';
}
