<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\DTO\GeneratedRefreshToken;
use App\Identity\Application\Service\RefreshTokenGeneratorInterface;
use App\Identity\Domain\RefreshToken\ValueObject\TokenHash;

/**
 * Gera refresh tokens opacos com 256 bits de entropia.
 *
 * O formato é `rt_` + 64 caracteres hexadecimais. O valor bruto nunca é
 * persistido; apenas o SHA-256 é devolvido em `GeneratedRefreshToken::$hash`.
 */
final readonly class OpaqueRefreshTokenGenerator implements RefreshTokenGeneratorInterface
{
    private const string PREFIX = 'rt_';

    public function generate(): GeneratedRefreshToken
    {
        $raw = self::PREFIX.bin2hex(random_bytes(32));

        return new GeneratedRefreshToken(
            raw: $raw,
            hash: TokenHash::fromRaw($raw),
        );
    }

    public function hash(string $rawToken): TokenHash
    {
        return TokenHash::fromRaw($rawToken);
    }
}
