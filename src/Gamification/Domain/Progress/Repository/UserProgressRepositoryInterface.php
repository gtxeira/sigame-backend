<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Progress\Repository;

use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Shared\Domain\ValueObject\Instant;

interface UserProgressRepositoryInterface
{
    public function findByUserId(PlayerId $userId): ?UserProgress;

    public function findByUserIdForUpdate(PlayerId $userId): ?UserProgress;

    public function save(UserProgress $progress): void;

    /**
     * @return list<UserProgress>
     */
    public function findDueForDecay(Instant $before): array;

    /**
     * @return list<UserProgress>
     */
    public function findAll(): array;

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed;
}
