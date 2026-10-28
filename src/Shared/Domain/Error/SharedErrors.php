<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * Fábricas de erros de domínio do kernel compartilhado.
 *
 * Único ponto autorizado a montar um DomainError para estes códigos,
 * evitando divergência de mensagem e field entre chamadas.
 */
final class SharedErrors
{
    public static function cpfInvalidLength(): DomainError
    {
        return new DomainError(
            code: SharedErrorCode::CpfInvalid->value,
            message: 'O CPF deve conter exatamente 11 dígitos.',
            field: 'cpf',
        );
    }

    public static function cpfAllDigitsEqual(): DomainError
    {
        return new DomainError(
            code: SharedErrorCode::CpfInvalid->value,
            message: 'O CPF não pode conter todos os dígitos iguais.',
            field: 'cpf',
        );
    }

    public static function cpfInvalidCheckDigits(): DomainError
    {
        return new DomainError(
            code: SharedErrorCode::CpfInvalid->value,
            message: 'O CPF possui dígitos verificadores inválidos.',
            field: 'cpf',
        );
    }

    public static function emailInvalid(): DomainError
    {
        return new DomainError(
            code: SharedErrorCode::EmailInvalid->value,
            message: 'O e-mail informado é inválido.',
            field: 'email',
        );
    }

    public static function dateTimeInvalidFormat(): DomainError
    {
        return new DomainError(
            code: SharedErrorCode::DateTimeInvalid->value,
            message: 'Informe uma data RFC 3339 (com ou sem milissegundos) com timezone.',
            field: 'datetime',
        );
    }

    public static function dateTimeInvalidTimestamp(): DomainError
    {
        return new DomainError(
            code: SharedErrorCode::DateTimeInvalid->value,
            message: 'O timestamp informado é inválido.',
            field: 'timestamp',
        );
    }

    public static function dateTimeInvalidAmount(string $field, string $operation): DomainError
    {
        return new DomainError(
            code: SharedErrorCode::DateTimeInvalid->value,
            message: sprintf('Informe um valor positivo maior que zero para %s %s.', $operation, $field),
            field: $field,
        );
    }

    public static function dateTimeInvalidShift(string $unit): DomainError
    {
        return new DomainError(
            code: SharedErrorCode::DateTimeInvalid->value,
            message: sprintf('Não foi possível modificar o datetime em %s.', $unit),
            field: $unit,
        );
    }
}
