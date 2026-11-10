<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Rule;

/**
 * Bônus de constância semanal.
 */
final readonly class WeeklyXpPolicy
{
    public function start(): int
    {
        return GamificationRules::WEEKLY_START_XP;
    }

    /**
     * Bônus da meta semanal para a sequência informada.
     *
     * A sequência considera a semana corrente; a primeira semana completa
     * recebe o valor base.
     */
    public function goal(int $streak): int
    {
        $steps = max(0, min(6, $streak - 1));

        return min(
            GamificationRules::WEEKLY_GOAL_BASE_XP + (GamificationRules::WEEKLY_GOAL_STEP_XP * $steps),
            GamificationRules::WEEKLY_GOAL_MAX_XP,
        );
    }
}
