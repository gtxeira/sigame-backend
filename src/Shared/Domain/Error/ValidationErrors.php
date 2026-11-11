<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * Fábricas de erros de forma/validação de campo.
 *
 * A mensagem é sempre informada pelo chamador para preservar o contexto
 * (limites, formato esperado, rótulo do campo). O código é o contrato público.
 */
final class ValidationErrors
{
    public static function fieldRequired(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::FieldRequired, $field, $message);
    }

    public static function invalidType(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::InvalidType, $field, $message);
    }

    public static function invalidFormat(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::InvalidFormat, $field, $message);
    }

    public static function stringTooShort(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::StringTooShort, $field, $message);
    }

    public static function stringTooLong(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::StringTooLong, $field, $message);
    }

    public static function numberOutOfRange(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::NumberOutOfRange, $field, $message);
    }

    public static function invalidChoice(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::InvalidChoice, $field, $message);
    }

    public static function tooFewItems(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::TooFewItems, $field, $message);
    }

    public static function tooManyItems(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::TooManyItems, $field, $message);
    }

    public static function unsupportedFileType(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::UnsupportedFileType, $field, $message);
    }

    public static function fileTooLarge(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::FileTooLarge, $field, $message);
    }

    public static function invalidImageDimensions(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::InvalidImageDimensions, $field, $message);
    }

    public static function invalidImageAspectRatio(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::InvalidImageAspectRatio, $field, $message);
    }

    public static function invalidImage(string $field, string $message): DomainError
    {
        return self::error(ValidationErrorCode::InvalidImage, $field, $message);
    }

    private static function error(ValidationErrorCode $code, string $field, string $message): DomainError
    {
        return new DomainError(
            code: $code->value,
            message: $message,
            kind: $code->kind(),
            field: $field,
        );
    }
}
