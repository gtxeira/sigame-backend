<?php

declare(strict_types=1);

namespace App\Identity\Application\DTO;

use App\Shared\Domain\ValueObject\Instant;

/**
 * Par de tokens devolvido ao cliente.
 */
final readonly class TokenPair
{
    public function __construct(
        public IssuedToken $accessToken,
        public string $refreshToken,
        public Instant $refreshExpiresAt,
    ) {
    }
}
