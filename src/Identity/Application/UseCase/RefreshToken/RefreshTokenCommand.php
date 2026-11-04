<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\RefreshToken;

/**
 * Command de renovação do par de tokens a partir do refresh token opaco.
 */
final readonly class RefreshTokenCommand
{
    public function __construct(
        private string $refreshToken,
    ) {
    }

    public function refreshToken(): string
    {
        return $this->refreshToken;
    }
}
