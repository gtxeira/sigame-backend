<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use App\Shared\Domain\Error\ErrorKind;
use App\Shared\Domain\Error\SharedErrorCode;

/**
 * Exceção para UUID inválido.
 */
final class InvalidUuidException extends InvalidArgumentException
{
    public function errorCode(): string
    {
        return SharedErrorCode::InvalidUuid->value;
    }

    public function kind(): ErrorKind
    {
        return SharedErrorCode::InvalidUuid->kind();
    }

    public function publicMessage(): string
    {
        return 'O identificador informado não é válido.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'id',
                'message' => 'O identificador deve ser um UUID v7 válido.',
            ],
        ];
    }
}
