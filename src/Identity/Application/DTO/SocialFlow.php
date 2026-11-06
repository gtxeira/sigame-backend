<?php

declare(strict_types=1);

namespace App\Identity\Application\DTO;

use App\Identity\Domain\User\ValueObject\IdentityProvider;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Estado server-side de um fluxo OAuth em andamento.
 *
 * O `state` é comparado no callback e o `codeVerifier` nunca trafega para o
 * cliente: o backend é quem troca o authorization code.
 */
final readonly class SocialFlow
{
    public function __construct(
        public string $id,
        public IdentityProvider $provider,
        public string $client,
        public string $redirectUri,
        public string $codeVerifier,
        public SocialFlowPurpose $purpose,
        public ?UserId $userId,
        public string $state,
        public Instant $expiresAt,
    ) {
    }

    public function isExpiredAt(Instant $now): bool
    {
        return !$this->expiresAt->isAfter($now);
    }
}
