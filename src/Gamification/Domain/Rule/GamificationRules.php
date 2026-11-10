<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Rule;

/**
 * Constantes da versão inicial das regras de gamificação.
 *
 * Qualquer mudança de valor deve gerar uma nova `ruleVersion` e um plano de
 * recálculo para não reduzir o XP já conquistado.
 */
final class GamificationRules
{
    public const string VERSION = '2026-11-v1';
    public const string TIMEZONE = 'America/Sao_Paulo';

    public const int MIN_LEVEL = 1;
    public const int MAX_LEVEL = 10;
    public const int ONBOARDING_XP = 100;

    public const int WEEKLY_START_XP = 10;
    public const int WEEKLY_GOAL_BASE_XP = 20;
    public const int WEEKLY_GOAL_STEP_XP = 5;
    public const int WEEKLY_GOAL_MAX_XP = 50;

    public const int WEEKEND_MULTIPLIER_PERCENT = 120;
    public const int MAX_XP_PER_ACTIVITY = 100;
    public const int FULL_XP_ACTIVITIES_PER_DAY = 3;
    public const int FOURTH_ACTIVITY_PERCENT = 25;

    public const int DECAY_GRACE_DAYS = 7;
    public const int DECAY_PERIOD_DAYS = 2;
    public const int DECAY_PERCENT = 2;
    public const int DECAY_FLOOR_LEVEL = 2;

    public const int MIN_CHECKED_IN_FOR_XP = 2;
}
