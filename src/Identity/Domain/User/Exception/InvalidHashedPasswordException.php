<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\Exception\IdentityException;
use App\Shared\Domain\Error\ErrorKind;

/**
 * Exceção para hash de senha inválido.
 */
final class InvalidHashedPasswordException extends IdentityException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::InvalidHashedPassword->value;
    }

    public function kind(): ErrorKind
    {
        return IdentityErrorCode::InvalidHashedPassword->kind();
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
