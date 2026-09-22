<?php

declare(strict_types=1);

namespace App\Identity\Domain\ValueObject;

/**
 * Value Object para status da conta do usuário.
 */
enum UserStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
}
