<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * Fábricas de erros de domínio do kernel compartilhado.
 *
 * Único ponto autorizado a montar um DomainError para estes códigos,
 * evitando divergência de mensagem, field e kind entre chamadas.
 */
final class SharedErrors
{
    public static function cpfInvalidLength(): DomainError
    {
        return self::error(
            code: SharedErrorCode::CpfInvalid,
            message: 'O CPF deve conter exatamente 11 dígitos.',
            field: 'cpf',
        );
    }

    public static function cpfAllDigitsEqual(): DomainError
    {
        return self::error(
            code: SharedErrorCode::CpfInvalid,
            message: 'O CPF não pode conter todos os dígitos iguais.',
            field: 'cpf',
        );
    }

    public static function cpfInvalidCheckDigits(): DomainError
    {
        return self::error(
            code: SharedErrorCode::CpfInvalid,
            message: 'O CPF possui dígitos verificadores inválidos.',
            field: 'cpf',
        );
    }

    public static function emailInvalid(): DomainError
    {
        return self::error(
            code: SharedErrorCode::EmailInvalid,
            message: 'O e-mail informado é inválido.',
            field: 'email',
        );
    }

    public static function dateTimeInvalidFormat(): DomainError
    {
        return self::error(
            code: SharedErrorCode::DateTimeInvalid,
            message: 'Informe uma data RFC 3339 (com ou sem milissegundos) com timezone.',
            field: 'datetime',
        );
    }

    public static function dateTimeInvalidTimestamp(): DomainError
    {
        return self::error(
            code: SharedErrorCode::DateTimeInvalid,
            message: 'O timestamp informado é inválido.',
            field: 'timestamp',
        );
    }

    public static function dateTimeInvalidAmount(string $field, string $operation): DomainError
    {
        return self::error(
            code: SharedErrorCode::DateTimeInvalid,
            message: sprintf('Informe um valor positivo maior que zero para %s %s.', $operation, $field),
            field: $field,
        );
    }

    public static function dateTimeInvalidShift(string $unit): DomainError
    {
        return self::error(
            code: SharedErrorCode::DateTimeInvalid,
            message: sprintf('Não foi possível modificar o datetime em %s.', $unit),
            field: $unit,
        );
    }

    private static function error(
        SharedErrorCode $code,
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
