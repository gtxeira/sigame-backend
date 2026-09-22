<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

/**
 * Exceção: usuário já está suspenso.
 */
final class UserAlreadySuspendedException extends IdentityException
{
    public function errorCode(): string
    {
        return 'USER_ALREADY_SUSPENDED';
    }

    public function publicMessage(): string
    {
        return 'This user account is already suspended.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'status',
                'message' => 'User is already suspended.',
            ],
        ];
    }
}
