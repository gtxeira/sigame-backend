<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Exceção para hash de senha inválido.
 */
final class InvalidHashedException extends InvalidArgumentException
{
    public function errorCode(): string
    {
        return 'INVALID_HASHED_PASSWORD';
    }

    public function publicMessage(): string
    {
        return 'The provided password hash is not valid.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'password',
                'message' => 'Password hash cannot be empty.',
            ],
        ];
    }
}
