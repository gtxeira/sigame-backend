<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http\Response;

use App\Identity\Application\DTO\TokenPair;
use App\Shared\Domain\Functional\Result;
use App\Shared\Presentation\Http\Response\ResultResponder;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Monta a resposta de sucesso do par de tokens e entrega o refresh no cookie.
 */
final readonly class TokenPairResponseFactory
{
    public function __construct(
        private ResultResponder $resultResponder,
        private RefreshTokenCookieFactory $cookieFactory,
    ) {
    }

    /**
     * @param Result<TokenPair> $result
     */
    public function fromResult(Result $result): JsonResponse
    {
        if ($result->isFailure()) {
            return $this->resultResponder->failure($result);
        }

        /** @var TokenPair $pair */
        $pair = $result->value();

        $response = $this->resultResponder->success(
            $result->map(static fn (TokenPair $tokenPair): TokenResponse => TokenResponse::fromTokenPair($tokenPair)),
        );
        $response->headers->setCookie($this->cookieFactory->create(
            rawToken: $pair->refreshToken,
            expiresAt: $pair->refreshExpiresAt,
        ));

        return $response;
    }
}
