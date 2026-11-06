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
    private function __construct(
        string $message,
        private readonly bool $expired = false,
    ) {
        parent::__construct($message);
    }

    public static function accessToken(string $reason): self
    {
        return new self('Access token inválido: '.$reason);
    }

    public static function accessTokenExpired(): self
    {
        return new self('Access token expirado.', expired: true);
    }

    public function isExpired(): bool
    {
        return $this->expired;
    }
}
