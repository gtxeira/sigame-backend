<?php

declare(strict_types=1);

namespace App\Shared\Domain\Error;

/**
 * Catálogo de códigos de erro do kernel compartilhado.
 *
 * O valor de cada caso é contrato público com o cliente da API:
 * alterá-lo é breaking change; renomear o caso não é.
 */
enum SharedErrorCode: string
{
    case CpfInvalid = 'CPF_INVALID';
    case EmailInvalid = 'EMAIL_INVALID';
    case DateTimeInvalid = 'DATE_TIME_INVALID';
    case InvalidUuid = 'INVALID_UUID';
    case InvalidHashedPassword = 'INVALID_HASHED_PASSWORD';

    public function kind(): ErrorKind
    {
        return match ($this) {
            self::CpfInvalid,
            self::EmailInvalid,
            self::DateTimeInvalid,
            self::InvalidUuid,
            self::InvalidHashedPassword => ErrorKind::Validation,
        };
    }
}
