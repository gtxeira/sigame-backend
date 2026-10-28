<?php

declare(strict_types=1);

namespace App\Identity\Domain\Error;

use App\Shared\Domain\Error\DomainError;

/**
 * Fábricas de erros de domínio esperados do módulo Identity.
 *
 * Único ponto autorizado a montar um DomainError para estes códigos,
 * evitando divergência de mensagem e field entre chamadas.
 */
final class IdentityErrors
{
    public static function cpfAlreadyExists(): DomainError
    {
        return new DomainError(
            code: IdentityErrorCode::UserCpfAlreadyExists->value,
            message: 'Já existe um usuário com este CPF.',
            field: 'cpf',
        );
    }

    public static function emailAlreadyExists(): DomainError
    {
        return new DomainError(
            code: IdentityErrorCode::EmailAlreadyExists->value,
            message: 'Já existe um usuário com este e-mail.',
            field: 'email',
        );
    }

    public static function userLevelBelowMinimum(int $minimum): DomainError
    {
        return new DomainError(
            code: IdentityErrorCode::UserLevelInvalid->value,
            message: 'O nível do usuário deve ser maior ou igual a '.$minimum,
            field: 'level',
        );
    }

    public static function userLevelAboveMaximum(int $maximum): DomainError
    {
        return new DomainError(
            code: IdentityErrorCode::UserLevelInvalid->value,
            message: 'O nível do usuário deve ser menor ou igual a '.$maximum,
            field: 'level',
        );
    }
}
