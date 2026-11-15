<?php

declare(strict_types=1);

namespace App\Identity\Domain\PasswordReset\ValueObject;

use App\Shared\Domain\ValueObject\BaseId;

/**
 * Value Object tipado para identificadores de tickets de reset de senha.
 */
final readonly class PasswordResetTicketId extends BaseId
{
}
