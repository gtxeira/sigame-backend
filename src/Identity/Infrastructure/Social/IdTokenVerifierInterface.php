<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Social;

/**
 * Porta para validar id_token de provedores OIDC.
 */
interface IdTokenVerifierInterface
{
    /**
     * @param list<string> $allowedIssuers
     *
     * @return array<string, mixed>
     */
    public function verify(
        string $idToken,
        string $jwksUrl,
        string $audience,
        array $allowedIssuers,
    ): array;
}
