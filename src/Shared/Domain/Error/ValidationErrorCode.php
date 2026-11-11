<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * Catálogo de códigos de erro de forma/validação de campo.
 *
 * Usado por DTOs e Value Objects para erros redutíveis a uma constraint:
 * presença, tipo, formato, tamanho, faixa, escolha, coleção e arquivo.
 * Regras de negócio, conflito, autenticação, estado e recurso inexistente
 * continuam usando o catálogo do módulo.
 */
enum ValidationErrorCode: string
{
    case FieldRequired = 'FIELD_REQUIRED';
    case InvalidType = 'INVALID_TYPE';
    case InvalidFormat = 'INVALID_FORMAT';
    case StringTooShort = 'STRING_TOO_SHORT';
    case StringTooLong = 'STRING_TOO_LONG';
    case NumberOutOfRange = 'NUMBER_OUT_OF_RANGE';
    case InvalidChoice = 'INVALID_CHOICE';
    case TooFewItems = 'TOO_FEW_ITEMS';
    case TooManyItems = 'TOO_MANY_ITEMS';
    case UnsupportedFileType = 'UNSUPPORTED_FILE_TYPE';
    case FileTooLarge = 'FILE_TOO_LARGE';
    case InvalidImageDimensions = 'INVALID_IMAGE_DIMENSIONS';
    case InvalidImageAspectRatio = 'INVALID_IMAGE_ASPECT_RATIO';
    case InvalidImage = 'INVALID_IMAGE';

    public function kind(): ErrorKind
    {
        return ErrorKind::Validation;
    }
}
