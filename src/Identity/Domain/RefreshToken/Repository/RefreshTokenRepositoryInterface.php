<?php

declare(strict_types=1);

namespace App\Identity\Domain\RefreshToken\Repository;

use App\Identity\Domain\RefreshToken\Entity\RefreshToken;
use App\Identity\Domain\RefreshToken\ValueObject\TokenFamilyId;
use App\Identity\Domain\RefreshToken\ValueObject\TokenHash;
use App\Identity\Domain\User\ValueObject\UserId;

/**
 * Porta de persistência dos refresh tokens.
 */
interface RefreshTokenRepositoryInterface
{
    public function save(RefreshToken $token): void;

    public function findByTokenHash(TokenHash $hash): ?RefreshToken;

    /**
     * Revoga o token atual e persiste o substituto na mesma transação.
     */
    public function rotate(RefreshToken $current, RefreshToken $replacement): void;

    public function revokeFamily(TokenFamilyId $familyId): int;

    public function revokeAllForUser(UserId $userId): int;

    /**
     * Revoga as famílias mais antigas mantendo no máximo `$keep` ativas.
     */
    public function keepNewestFamilies(UserId $userId, int $keep): void;
}
