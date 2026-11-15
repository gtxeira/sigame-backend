<?php

declare(strict_types=1);

namespace App\Identity\Domain\PasswordReset\Repository;

use App\Identity\Domain\PasswordReset\Entity\PasswordResetTicket;
use App\Identity\Domain\User\Entity\User;

/**
 * Porta de persistência dos tickets de reset de senha.
 */
interface PasswordResetTicketRepositoryInterface
{
    public function save(PasswordResetTicket $ticket): void;

    public function findByHash(string $tokenHash): ?PasswordResetTicket;

    /**
     * Marca o ticket como consumido de forma atômica.
     *
     * @return bool true quando esta chamada foi a que consumiu o ticket
     */
    public function markConsumed(PasswordResetTicket $ticket): bool;

    public function invalidateActive(User $user): void;
}
