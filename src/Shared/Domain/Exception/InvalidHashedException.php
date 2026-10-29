<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use App\Shared\Domain\Error\ErrorKind;
use App\Shared\Domain\Error\SharedErrorCode;

/**
 * Exceção para hash de senha inválido.
 */
final class InvalidHashedException extends InvalidArgumentException
{
    public function errorCode(): string
    {
        return SharedErrorCode::InvalidHashedPassword->value;
    }

    public function kind(): ErrorKind
    {
        return SharedErrorCode::InvalidHashedPassword->kind();
    }

    public function publicMessage(): string
    {
        return 'O hash de senha informado não é válido.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'password',
                'message' => 'O hash da senha não pode ser vazio.',
            ],
        ];
    }
}
