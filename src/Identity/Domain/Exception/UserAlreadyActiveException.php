<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Shared\Domain\Error\ErrorKind;

/**
 * Exceção: usuário já está ativo.
 */
final class UserAlreadyActiveException extends IdentityException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::UserAlreadyActive->value;
    }

    public function kind(): ErrorKind
    {
        return IdentityErrorCode::UserAlreadyActive->kind();
    }

    public function publicMessage(): string
    {
        return 'Esta conta de usuário já está ativa.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'status',
                'message' => 'O usuário já está ativo.',
            ],
        ];
    }
}
