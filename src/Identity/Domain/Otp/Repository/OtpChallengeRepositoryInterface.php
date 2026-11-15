<?php

declare(strict_types=1);

namespace App\Identity\Domain\Otp\Repository;

use App\Identity\Domain\Otp\Entity\OtpChallenge;
use App\Identity\Domain\Otp\ValueObject\OtpChallengeId;
use App\Identity\Domain\Otp\ValueObject\OtpPurpose;
use App\Identity\Domain\User\Entity\User;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Porta de persistência dos desafios OTP da identidade.
 */
interface OtpChallengeRepositoryInterface
{
    public function save(OtpChallenge $challenge): void;

    public function findById(OtpChallengeId $id): ?OtpChallenge;

    /**
     * Retorna o desafio não consumido do usuário e propósito, mesmo expirado.
     */
    public function findActive(User $user, OtpPurpose $purpose): ?OtpChallenge;

    /**
     * Marca o desafio como consumido de forma atômica.
     *
     * @return bool true quando esta chamada foi a que consumiu o desafio
     */
    public function markConsumed(OtpChallenge $challenge): bool;

    /**
     * Incrementa as tentativas de forma atômica, invalidando ao atingir o limite.
     *
     * @return int total de tentativas após o incremento
     */
    public function recordFailedAttempt(OtpChallenge $challenge, int $maxAttempts): int;

    public function invalidateActive(User $user, OtpPurpose $purpose): void;

    /**
     * Informa se existe desafio não consumido do propósito criado depois do instante.
     */
    public function hasActiveCreatedAfter(
        User $user,
        OtpPurpose $purpose,
        Instant $since,
    ): bool;

    /**
     * Retorna o instante de criação do desafio ativo mais recente do propósito.
     */
    public function findActiveCreatedAt(User $user, OtpPurpose $purpose): ?Instant;
}
