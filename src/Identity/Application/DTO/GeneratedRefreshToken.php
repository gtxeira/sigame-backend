<?php

declare(strict_types=1);

namespace App\Identity\Application\DTO;

use App\Identity\Domain\RefreshToken\ValueObject\TokenHash;

/**
 * Refresh token opaco recém-gerado.
 *
 * `raw` só pode ir para o cookie; `hash` é o que pode ser persistido.
 */
final readonly class GeneratedRefreshToken
{
    public function __construct(
        public string $raw,
        public TokenHash $hash,
    ) {
    }
}
