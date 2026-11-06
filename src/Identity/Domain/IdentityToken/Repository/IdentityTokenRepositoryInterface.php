<?php

declare(strict_types=1);

namespace App\Identity\Domain\IdentityToken\Repository;

use App\Identity\Domain\IdentityToken\Entity\IdentityToken;
use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenPurpose;
use App\Identity\Domain\User\Entity\User;

/**
 * Porta de persistência dos tokens de uso único da identidade.
 */
interface IdentityTokenRepositoryInterface
{
    public function save(IdentityToken $token): void;

    public function findByHash(string $tokenHash): ?IdentityToken;

    /**
     * Marca o token como consumido de forma atômica.
     *
     * @return bool true quando esta chamada foi a que consumiu o token
     */
    public function markConsumed(IdentityToken $token): bool;

    public function invalidateUnconsumed(User $user, IdentityTokenPurpose $purpose): void;
}
