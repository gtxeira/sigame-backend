<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Application\DTO\GeneratedRefreshToken;
use App\Identity\Domain\RefreshToken\ValueObject\TokenHash;

/**
 * Porta para geração e hashing do refresh token opaco.
 */
interface RefreshTokenGeneratorInterface
{
    public function generate(): GeneratedRefreshToken;

    public function hash(string $rawToken): TokenHash;
}
