<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\GetGamificationRules;

/**
 * Regras de deleveling por inatividade.
 */
final readonly class DecayRulesView
{
    public function __construct(
        public int $graceDays,
        public int $periodDays,
        public int $barPercentPerPeriod,
        public int $floorLevel,
        public int $levelDropBarPercent,
        public bool $usesValidationInstant,
    ) {
    }
}
