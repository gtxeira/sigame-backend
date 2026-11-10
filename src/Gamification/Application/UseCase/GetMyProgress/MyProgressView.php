<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\GetMyProgress;

use App\Gamification\Domain\Streak\ValueObject\WeeklyStreakState;

/**
 * Fotografia do progresso usada pela API.
 */
final readonly class MyProgressView
{
    public function __construct(
        public int $level,
        public int $xpBalance,
        public int $xpLifetime,
        public int $levelStartXp,
        public ?int $nextLevelXp,
        public ?int $xpToNextLevel,
        public float $barPercent,
        public int $currentWeeklyStreak,
        public int $bestWeeklyStreak,
        public int $weeklyGoal,
        public int $weeklyActivities,
        public string $weekKey,
        public string $weekStart,
        public string $weekEnd,
        public WeeklyStreakState $state,
        public string $ruleVersion,
    ) {
    }
}
