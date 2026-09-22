<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Exceção para nível de usuário inválido.
 */
final class InvalidUserLevelException extends InvalidArgumentException
{
    public function errorCode(): string
    {
        return 'INVALID_USER_LEVEL';
    }

    public function publicMessage(): string
    {
        return 'The provided user level is not valid.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'level',
                'message' => 'User level must be between 1 and 10.',
            ],
        ];
    }
}
