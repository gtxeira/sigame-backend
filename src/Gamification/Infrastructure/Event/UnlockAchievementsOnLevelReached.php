<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Event;

use App\Gamification\Application\Service\AchievementUnlocker;
use App\Gamification\Domain\Progress\Event\LevelReached;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Shared\Domain\Exception\InvalidUuidException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Registra os marcos alcançados quando o nível do usuário sobe.
 */
#[AsEventListener(event: 'gamification.level.reached')]
final readonly class UnlockAchievementsOnLevelReached
{
    public function __construct(
        private AchievementUnlocker $achievementUnlocker,
    ) {
    }

    public function __invoke(LevelReached $event): void
    {
        try {
            $userId = PlayerId::fromString($event->userId());
        } catch (InvalidUuidException) {
            return;
        }

        $this->achievementUnlocker->unlock($userId, $event->level(), $event->occurredAt());
    }
}
