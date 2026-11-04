<?php

declare(strict_types=1);

namespace App\Identity\Application\DTO;

use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Claims relevantes de um access token já validado.
 */
final readonly class AccessTokenClaims
{
    public function __construct(
        public UserId $userId,
        public Instant $expiresAt,
    ) {
    }
}
