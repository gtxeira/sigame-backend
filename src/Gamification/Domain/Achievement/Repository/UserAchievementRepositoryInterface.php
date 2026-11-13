<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Achievement\Repository;

use App\Gamification\Domain\Achievement\Entity\UserAchievement;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;

interface UserAchievementRepositoryInterface
{
    /**
     * @return list<UserAchievement>
     */
    public function findByUserId(PlayerId $userId): array;

    public function findByUserIdAndCode(PlayerId $userId, AchievementCode $code): ?UserAchievement;

    /**
     * Insere a conquista se ainda não existir (idempotente).
     *
     * Usa `INSERT … ON CONFLICT DO NOTHING` para que duas requisições
     * concorrentes não fechem o EntityManager com uma violação de unicidade.
     *
     * @return bool `true` quando a linha foi criada
     */
    public function insertIfAbsent(UserAchievement $achievement): bool;

    public function save(UserAchievement $achievement): void;

    public function deleteByUserId(PlayerId $userId): void;
}
