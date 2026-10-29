<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\ValueObject;

/**
 * Value Object para papéis de usuário.
 */
enum UserRole: string
{
    case USER = 'USER';
    case ADMIN_SIGAME = 'ADMIN_SIGAME';
}
