<?php

declare(strict_types=1);

namespace App\Identity\Domain\Error;

use App\Shared\Domain\Error\DomainError;

/**
 * Fábricas de erros de domínio esperados do módulo Identity.
 *
 * Único ponto autorizado a montar um DomainError para estes códigos,
 * evitando divergência de mensagem, field e kind entre chamadas.
 */
final class IdentityErrors
{
    public static function cpfAlreadyExists(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::UserCpfAlreadyExists,
            message: 'Já existe um usuário com este CPF.',
            field: 'cpf',
        );
    }

    public static function emailAlreadyExists(): DomainError
    {
        return self::error(
            code: IdentityErrorCode::EmailAlreadyExists,
            message: 'Já existe um usuário com este e-mail.',
            field: 'email',
        );
    }

    public static function userLevelBelowMinimum(int $minimum): DomainError
    {
        return self::error(
            code: IdentityErrorCode::UserLevelInvalid,
            message: 'O nível do usuário deve ser maior ou igual a '.$minimum,
            field: 'level',
        );
    }

    public static function userLevelAboveMaximum(int $maximum): DomainError
    {
        return self::error(
            code: IdentityErrorCode::UserLevelInvalid,
            message: 'O nível do usuário deve ser menor ou igual a '.$maximum,
            field: 'level',
        );
    }

    public static function userHeightInvalid(int $minimum, int $maximum): DomainError
    {
        return self::error(
            code: IdentityErrorCode::UserHeightInvalid,
            message: sprintf('A altura deve estar entre %d e %d centímetros.', $minimum, $maximum),
            field: 'heightCm',
        );
    }

    public static function userWeightInvalid(float $minimum, float $maximum): DomainError
    {
        return self::error(
            code: IdentityErrorCode::UserWeightInvalid,
            message: sprintf('O peso deve estar entre %s e %s quilogramas.', $minimum, $maximum),
            field: 'weightKg',
        );
    }

    private static function error(
        IdentityErrorCode $code,
        string $message,
        ?string $field = null,
    ): DomainError {
        return new DomainError(
            code: $code->value,
            message: $message,
            kind: $code->kind(),
            field: $field,
        );
    }
}
