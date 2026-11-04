<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use App\Identity\Application\DTO\TokenPair;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\ValueObject\UserId;
use App\Shared\Domain\Functional\Result;

/**
 * Porta de emissão, rotação e revogação do par de tokens.
 */
interface TokenPairIssuerInterface
{
    public function issueForNewSession(User $user): TokenPair;

    /**
     * @return Result<TokenPair>
     */
    public function rotate(string $rawRefreshToken): Result;

    public function revokeCurrentSession(string $rawRefreshToken): void;

    public function revokeAllSessions(UserId $userId): void;
}
