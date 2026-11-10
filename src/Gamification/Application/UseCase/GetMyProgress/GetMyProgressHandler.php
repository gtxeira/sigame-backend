<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\GetMyProgress;

use App\Gamification\Application\Service\ProgressProvider;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Rule\GamificationRules;
use App\Gamification\Domain\Rule\LevelCurve;
use App\Gamification\Domain\Streak\Service\StreakCalculator;
use App\Gamification\Domain\Streak\Service\StreakCalendar;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;

/**
 * Monta a fotografia do progresso do usuário autenticado.
 */
final readonly class GetMyProgressHandler
{
    public function __construct(
        private ProgressProvider $progressProvider,
        private UserProgressRepositoryInterface $progressRepository,
        private XpEventRepositoryInterface $xpEventRepository,
        private LevelCurve $levelCurve,
        private StreakCalendar $calendar,
        private StreakCalculator $streakCalculator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PlayerId $userId): MyProgressView
    {
        $progress = $this->progressProvider->getOrCreate($userId);
        $this->progressRepository->save($progress);

        $activityDates = $this->xpEventRepository->findActiveActivityDates($userId);
        $today = $this->calendar->localDate(Instant::fromDateTime($this->clock->now()));
        $weekKey = $this->calendar->weekKeyForDate($today);
        $weeklyActivities = $this->streakCalculator->weeklyCount($activityDates, $weekKey);
        $currentStreak = $this->streakCalculator->current($activityDates, $today);
        $bestStreak = max($progress->bestWeeklyStreak(), $this->streakCalculator->best($activityDates));

        $level = $this->levelCurve->levelFor($progress->xpBalance());
        $nextLevelXp = $this->levelCurve->nextLevelXp($level);

        return new MyProgressView(
            level: $level,
            xpBalance: $progress->xpBalance(),
            xpLifetime: $progress->xpLifetime(),
            levelStartXp: $this->levelCurve->threshold($level),
            nextLevelXp: $nextLevelXp,
            xpToNextLevel: null === $nextLevelXp ? null : max(0, $nextLevelXp - $progress->xpBalance()),
            barPercent: $this->levelCurve->barPercent($level, $progress->xpBalance()),
            currentWeeklyStreak: $currentStreak,
            bestWeeklyStreak: $bestStreak,
            weeklyGoal: 2,
            weeklyActivities: $weeklyActivities,
            weekKey: $weekKey,
            weekStart: $weekKey,
            weekEnd: $this->calendar->addDays($weekKey, 6),
            state: $this->streakCalculator->state($weeklyActivities, $currentStreak),
            ruleVersion: GamificationRules::VERSION,
        );
    }
}
