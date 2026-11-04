<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\RefreshToken;

use App\Identity\Application\DTO\TokenPair;
use App\Identity\Application\Service\TokenPairIssuerInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Renova o par de tokens, rotacionando o refresh token.
 */
final readonly class RefreshTokenHandler
{
    public function __construct(
        private TokenPairIssuerInterface $tokenPairIssuer,
    ) {
    }

    /**
     * @return Result<TokenPair>
     */
    public function __invoke(RefreshTokenCommand $command): Result
    {
        return $this->tokenPairIssuer->rotate($command->refreshToken());
    }
}
