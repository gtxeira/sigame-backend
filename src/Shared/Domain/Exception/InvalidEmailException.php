<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Exceção para email inválido.
 */
final class InvalidEmailException extends InvalidArgumentException
{
    public function errorCode(): string
    {
        return 'INVALID_EMAIL';
    }

    public function publicMessage(): string
    {
        return 'The provided email address is not valid.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'email',
                'message' => 'Email must be a valid email address.',
            ],
        ];
    }
}
