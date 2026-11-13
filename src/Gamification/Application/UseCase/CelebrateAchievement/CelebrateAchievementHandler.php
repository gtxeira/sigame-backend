<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\CelebrateAchievement;

use App\Gamification\Application\UseCase\ListAchievements\AchievementView;
use App\Gamification\Domain\Achievement\Repository\UserAchievementRepositoryInterface;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use App\Gamification\Domain\Error\GamificationErrors;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Registra que a comemoração de uma conquista já foi exibida.
 *
 * A operação é idempotente: celebrar novamente devolve a data original.
 */
final readonly class CelebrateAchievementHandler
{
    public function __construct(
        private UserAchievementRepositoryInterface $repository,
    ) {
    }

    /**
     * @return Result<AchievementView>
     */
    public function __invoke(PlayerId $userId, string $code): Result
    {
        $achievementCode = AchievementCode::tryFrom($code);

        if (null === $achievementCode) {
            return Result::failure([GamificationErrors::achievementNotFound()]);
        }

        $achievement = $this->repository->findByUserIdAndCode($userId, $achievementCode);

        if (null === $achievement) {
            return Result::failure([GamificationErrors::achievementNotFound()]);
        }

        if ($achievement->celebrate(Instant::now())) {
            $this->repository->save($achievement);
        }

        return Result::success(AchievementView::fromEntity($achievement));
    }
}
