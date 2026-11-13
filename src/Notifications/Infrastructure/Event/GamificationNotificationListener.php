<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Event;

use App\Gamification\Domain\Achievement\Event\AchievementUnlocked;
use App\Gamification\Domain\Progress\Event\LevelLost;
use App\Gamification\Domain\Progress\Event\LevelReached;
use App\Gamification\Domain\Progress\Event\WeeklyGoalCompleted;
use App\Notifications\Application\Service\NotificationIntent;
use App\Notifications\Application\Service\NotificationRequestPublisher;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Traduz eventos de gamificação em intenções de notificação.
 */
final readonly class GamificationNotificationListener
{
    public function __construct(
        private NotificationRequestPublisher $publisher,
    ) {
    }

    #[AsEventListener(event: 'gamification.level.reached')]
    public function onLevelReached(LevelReached $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::LEVEL_REACHED,
            deduplicationKey: 'level-reached:'.$event->userId().':'.$event->level().':'.$event->occurredAt()->toIsoStringWithMicroseconds(),
            context: [
                'userId' => $event->userId(),
                'previousLevel' => $event->previousLevel(),
                'level' => $event->level(),
            ],
        ));
    }

    #[AsEventListener(event: 'gamification.level.lost')]
    public function onLevelLost(LevelLost $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::LEVEL_LOST,
            deduplicationKey: 'level-lost:'.$event->userId().':'.$event->level().':'.$event->occurredAt()->toIsoStringWithMicroseconds(),
            context: [
                'userId' => $event->userId(),
                'previousLevel' => $event->previousLevel(),
                'level' => $event->level(),
                'reason' => $event->reason(),
            ],
        ));
    }

    #[AsEventListener(event: 'gamification.weekly.goal.completed')]
    public function onWeeklyGoalCompleted(WeeklyGoalCompleted $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::WEEKLY_GOAL_COMPLETED,
            deduplicationKey: 'weekly-goal:'.$event->userId().':'.$event->weekKey(),
            context: [
                'userId' => $event->userId(),
                'weekKey' => $event->weekKey(),
                'streak' => $event->streak(),
                'xp' => $event->xp(),
            ],
        ));
    }

    #[AsEventListener(event: 'gamification.achievement.unlocked')]
    public function onAchievementUnlocked(AchievementUnlocked $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::ACHIEVEMENT_UNLOCKED,
            deduplicationKey: 'achievement-unlocked:'.$event->userId().':'.$event->code(),
            context: [
                'userId' => $event->userId(),
                'code' => $event->code(),
                'name' => $event->name(),
                'achievedAt' => $event->occurredAt()->toIsoStringWithMilliseconds(),
            ],
        ));
    }
}
