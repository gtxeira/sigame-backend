<?php

declare(strict_types=1);

namespace App\Identity\Application\DTO;

use App\Shared\Domain\ValueObject\Instant;

/**
 * Token emitido e seu instante de expiração.
 */
final readonly class IssuedToken
{
    public function __construct(
        public string $token,
        public Instant $expiresAt,
    ) {
    }
}
