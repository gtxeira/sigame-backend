<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Social;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Throwable;
use UnexpectedValueException;

/**
 * Valida um id_token OIDC contra o JWKS do provedor.
 */
final readonly class IdTokenVerifier implements IdTokenVerifierInterface
{
    public function __construct(
        private JwksProvider $jwksProvider,
    ) {
    }

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
    ): array {
        try {
            $keys = JWK::parseKeySet($this->jwksProvider->fetch($jwksUrl), 'RS256');
            /** @var array<string, mixed> $claims */
            $claims = (array) JWT::decode($idToken, $keys);
        } catch (Throwable $exception) {
            throw new UnexpectedValueException('Não foi possível validar o id_token.', 0, $exception);
        }

        $issuer = $claims['iss'] ?? null;

        if (!is_string($issuer) || !in_array($issuer, $allowedIssuers, true)) {
            throw new UnexpectedValueException('Emissor do id_token inválido.');
        }

        $tokenAudience = $claims['aud'] ?? null;
        $audienceMatches = is_array($tokenAudience)
            ? in_array($audience, $tokenAudience, true)
            : $tokenAudience === $audience;

        if (!$audienceMatches) {
            throw new UnexpectedValueException('Audiência do id_token inválida.');
        }

        return $claims;
    }
}
