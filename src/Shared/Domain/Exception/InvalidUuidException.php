<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Exceção para UUID inválido.
 */
final class InvalidUuidException extends InvalidArgumentException
{
    public function errorCode(): string
    {
        return 'INVALID_UUID';
    }

    public function publicMessage(): string
    {
        return 'The provided UUID is not valid.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'id',
                'message' => 'UUID must be a valid v7 (or compatible) UUID string.',
            ],
        ];
    }
}
