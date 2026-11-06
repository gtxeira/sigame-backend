<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Social;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Throwable;
use UnexpectedValueException;

/**
 * Valida um id_token OIDC contra o JWKS do provedor.
 *
 * Além da assinatura, exige emissor, audiência, expiração, emissão e, quando
 * aplicável, o authorized party (`azp`).
 */
final readonly class IdTokenVerifier implements IdTokenVerifierInterface
{
    private const int CLOCK_LEEWAY_SECONDS = 30;

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
        JWT::$leeway = self::CLOCK_LEEWAY_SECONDS;

        try {
            $jwks = $this->jwksProvider->fetch($jwksUrl);
            $keyId = self::keyId($idToken);

            if (null !== $keyId && !self::hasKey($jwks, $keyId)) {
                // Chave rotacionada: renova o JWKS uma vez antes de rejeitar.
                $jwks = $this->jwksProvider->refresh($jwksUrl);
            }

            /** @var array<string, mixed> $claims */
            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($jwks, 'RS256'));
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

        $authorizedParty = $claims['azp'] ?? null;

        if (is_array($tokenAudience) && count($tokenAudience) > 1 && $authorizedParty !== $audience) {
            throw new UnexpectedValueException('Authorized party do id_token inválido.');
        }

        if (null !== $authorizedParty && $authorizedParty !== $audience) {
            throw new UnexpectedValueException('Authorized party do id_token inválido.');
        }

        // O PHP-JWT só valida exp/iat quando presentes; exigir os dois evita
        // aceitar um token sem janela de validade.
        if (!isset($claims['exp']) || !is_int($claims['exp'])) {
            throw new UnexpectedValueException('id_token sem expiração.');
        }

        if (!isset($claims['iat']) || !is_int($claims['iat'])) {
            throw new UnexpectedValueException('id_token sem emissão.');
        }

        return $claims;
    }

    private static function keyId(string $idToken): ?string
    {
        $parts = explode('.', $idToken);

        if (3 !== count($parts)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $header */
            $header = (array) JWT::jsonDecode(JWT::urlsafeB64Decode($parts[0]));
        } catch (Throwable) {
            return null;
        }

        $kid = $header['kid'] ?? null;

        return is_string($kid) && '' !== $kid ? $kid : null;
    }

    /**
     * @param array{keys: list<array<string, mixed>>} $jwks
     */
    private static function hasKey(array $jwks, string $keyId): bool
    {
        foreach ($jwks['keys'] as $key) {
            if (($key['kid'] ?? null) === $keyId) {
                return true;
            }
        }

        return false;
    }
}
