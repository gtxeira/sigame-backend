<?php

declare(strict_types=1);

namespace App\Gamification\Application\Service;

use App\Gamification\Domain\Achievement\Entity\UserAchievement;
use App\Gamification\Domain\Achievement\Event\AchievementUnlocked;
use App\Gamification\Domain\Achievement\Repository\UserAchievementRepositoryInterface;
use App\Gamification\Domain\Achievement\Service\AchievementCatalog;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Registra os marcos alcançados a partir do nível do usuário.
 */
final readonly class AchievementUnlocker
{
    public function __construct(
        private UserAchievementRepositoryInterface $repository,
        private AchievementCatalog $catalog,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function unlock(PlayerId $userId, int $level, ?Instant $achievedAt = null): void
    {
        $achievedAt ??= Instant::now();

        foreach ($this->catalog->reachedAt($level) as $code) {
            $this->unlockCode($userId, $code, $achievedAt);
        }
    }

    private function unlockCode(PlayerId $userId, AchievementCode $code, Instant $achievedAt): void
    {
        if (null !== $this->repository->findByUserIdAndCode($userId, $code)) {
            return;
        }

        // `INSERT … ON CONFLICT DO NOTHING` mantém a operação idempotente mesmo
        // com duas requisições concorrentes, sem fechar o EntityManager.
        if (!$this->repository->insertIfAbsent(UserAchievement::unlock($userId, $code, $achievedAt))) {
            return;
        }

        $this->eventDispatcher->dispatch(AchievementUnlocked::now(
            userId: $userId->value(),
            code: $code->value,
            name: $code->label(),
        ));
    }
}
