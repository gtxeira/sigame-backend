<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

/**
 * Exceção: usuário já está ativo.
 */
final class UserAlreadyActiveException extends IdentityException
{
    public function errorCode(): string
    {
        return 'USER_ALREADY_ACTIVE';
    }

    public function publicMessage(): string
    {
        return 'This user account is already active.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'status',
                'message' => 'User is already active.',
            ],
        ];
    }
}
