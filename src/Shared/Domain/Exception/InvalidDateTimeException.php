<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Exceção para datetime inválido.
 */
final class InvalidDateTimeException extends InvalidArgumentException
{
    public function errorCode(): string
    {
        return 'INVALID_DATETIME';
    }

    public function publicMessage(): string
    {
        return 'The provided datetime value is not valid.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'datetime',
                'message' => 'Datetime must be a valid ISO 8601 string or timestamp.',
            ],
        ];
    }
}
