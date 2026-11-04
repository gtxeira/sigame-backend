<?php

declare(strict_types=1);

namespace App\Identity\Application\Exception;

use RuntimeException;

/**
 * Falha ao validar ou interpretar um token.
 *
 * É uma falha esperada: o adapter lança e o caso de uso converte em Result.
 */
final class InvalidTokenException extends RuntimeException
{
    public static function accessToken(string $reason): self
    {
        return new self('Access token inválido: '.$reason);
    }
}
