<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\GetGamificationRules;

/**
 * Regras da ofensiva e do bônus semanal.
 */
final readonly class WeeklyRulesView
{
    /**
     * @param list<array{streak: int, amount: int}> $goalBonusByStreak
     */
    public function __construct(
        public int $goalActivities,
        public int $startXp,
        public int $goalBaseXp,
        public int $goalStepXp,
        public int $goalMaxXp,
        public array $goalBonusByStreak,
        public int $goalBonusCapFromStreak,
        public string $weekStartsOn,
    ) {
    }
}
