<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * Catálogo de códigos de erro do kernel compartilhado.
 *
 * Erros de forma de campo ficam em `ValidationErrorCode`; este catálogo
 * reserva apenas códigos internos que não são expostos pela API.
 *
 * O valor de cada caso é contrato público com o cliente da API:
 * alterá-lo é breaking change; renomear o caso não é.
 */
enum SharedErrorCode: string
{
    case InvalidUuid = 'INVALID_UUID';

    public function kind(): ErrorKind
    {
        return match ($this) {
            self::InvalidUuid => ErrorKind::Validation,
        };
    }
}
