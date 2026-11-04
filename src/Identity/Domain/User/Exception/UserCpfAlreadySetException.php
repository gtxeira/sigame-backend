<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Exception;

use App\Identity\Domain\Error\IdentityErrorCode;
use App\Identity\Domain\Exception\IdentityException;
use App\Shared\Domain\Error\ErrorKind;

/**
 * Exceção: o CPF do usuário já foi definido e não pode ser alterado.
 */
final class UserCpfAlreadySetException extends IdentityException
{
    public function errorCode(): string
    {
        return IdentityErrorCode::UserCpfAlreadySet->value;
    }

    public function kind(): ErrorKind
    {
        return IdentityErrorCode::UserCpfAlreadySet->kind();
    }

    public function publicMessage(): string
    {
        return 'O CPF já foi definido e não pode ser alterado.';
    }

    public function errorDetails(): array
    {
        return [
            [
                'field' => 'cpf',
                'message' => 'O CPF é imutável depois de definido.',
            ],
        ];
    }
}
