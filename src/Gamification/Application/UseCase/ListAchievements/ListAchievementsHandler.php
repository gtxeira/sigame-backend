<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\ListAchievements;

use App\Gamification\Domain\Achievement\Entity\UserAchievement;
use App\Gamification\Domain\Achievement\Repository\UserAchievementRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;

/**
 * Lista as conquistas já desbloqueadas pelo usuário.
 */
final readonly class ListAchievementsHandler
{
    public function __construct(
        private UserAchievementRepositoryInterface $repository,
    ) {
    }

    /**
     * @return list<AchievementView>
     */
    public function __invoke(PlayerId $userId): array
    {
        return array_map(
            static fn (UserAchievement $achievement): AchievementView => AchievementView::fromEntity($achievement),
            $this->repository->findByUserId($userId),
        );
    }
}
