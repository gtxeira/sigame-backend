<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;

/**
 * Exceção: usuário já está suspenso.
 */
final class UserAlreadySuspendedException extends IdentityException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::UserAlreadySuspended->value;
    }

    public function publicMessage(): string
    {
        return 'Esta conta de usuário já está suspensa.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'status',
                'message' => 'O usuário já está suspenso.',
            ],
        ];
    }
}
