<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use App\Identity\Application\DTO\TokenPair;
use App\Shared\Domain\ValueObject\Instant;
use OpenApi\Attributes as OA;

/**
 * Resposta dos endpoints que emitem tokens.
 *
 * O refresh token não aparece aqui: ele vai apenas no cookie HttpOnly.
 */
#[OA\Schema(description: 'Access token emitido. O refresh token é entregue em cookie HttpOnly.')]
final readonly class TokenResponse
{
    public function __construct(
        #[OA\Property(description: 'Access token JWT.', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...')]
        public string $accessToken,

        #[OA\Property(description: 'Tipo do token.', example: 'Bearer')]
        public string $tokenType,

        #[OA\Property(description: 'Tempo de vida do access token em segundos.', example: 900)]
        public int $expiresIn,
    ) {
    }

    public static function fromTokenPair(TokenPair $pair): self
    {
        return new self(
            accessToken: $pair->accessToken->token,
            tokenType: 'Bearer',
            expiresIn: max(0, $pair->accessToken->expiresAt->timestamp() - Instant::now()->timestamp()),
        );
    }
}
