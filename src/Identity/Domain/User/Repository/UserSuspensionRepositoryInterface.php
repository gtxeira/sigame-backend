<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Repository;

use App\Identity\Domain\User\Entity\UserSuspension;
use App\Identity\Domain\User\ValueObject\UserId;

/**
 * Porta de persistência do histórico de suspensões.
 */
interface UserSuspensionRepositoryInterface
{
    public function save(UserSuspension $suspension): void;

    /**
     * @return list<UserSuspension>
     */
    public function findByUser(UserId $userId, int $limit = 50, int $offset = 0): array;

    public function countByUser(UserId $userId): int;
}
