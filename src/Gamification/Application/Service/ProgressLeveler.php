<?php

declare(strict_types=1);

namespace App\Gamification\Application\Service;

use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\Event\LevelLost;
use App\Gamification\Domain\Progress\Event\LevelReached;
use App\Gamification\Domain\Rule\LevelCurve;
use App\Shared\Application\Event\EventDispatcherInterface;

/**
 * Sincroniza o nível materializado com o saldo e publica a transição.
 */
final readonly class ProgressLeveler
{
    public function __construct(
        private LevelCurve $levelCurve,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function sync(UserProgress $progress, string $lostReason = 'xp_changed'): void
    {
        $level = $this->levelCurve->levelFor($progress->xpBalance());
        $previous = $progress->level();

        if (!$progress->changeLevel($level)) {
            return;
        }

        if ($level > $previous) {
            $this->eventDispatcher->dispatch(LevelReached::now(
                userId: $progress->userId()->value(),
                previousLevel: $previous,
                level: $level,
            ));

            return;
        }

        $this->eventDispatcher->dispatch(LevelLost::now(
            userId: $progress->userId()->value(),
            previousLevel: $previous,
            level: $level,
            reason: $lostReason,
        ));
    }
}
