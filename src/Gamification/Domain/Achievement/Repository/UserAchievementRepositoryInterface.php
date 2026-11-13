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

    public function save(UserAchievement $achievement): void;

    public function deleteByUserId(PlayerId $userId): void;
}
