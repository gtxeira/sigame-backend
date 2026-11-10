<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\GetGamificationRules;

/**
 * Fórmula e faixas do XP de atividade.
 */
final readonly class ActivityXpRulesView
{
    /**
     * @param list<array{modality: string, xp: int}>                                              $baseXpByModality
     * @param list<array{modality: string, bands: list<array{upToMeters: int|null, bonus: int}>}> $distanceBands
     * @param list<array{minMinutes: int, maxMinutes: int|null, bonus: int}>                      $durationBands
     * @param list<array{minCheckIns: int, maxCheckIns: int|null, bonus: int}>                    $checkInBands
     * @param list<array{minActivities: int, maxActivities: int|null, percent: int}>              $dailyFactorBands
     */
    public function __construct(
        public ActivityEligibilityView $eligibility,
        public int $maxXpPerActivity,
        public int $weekendMultiplierPercent,
        public int $durationBonusWhenMissing,
        public array $baseXpByModality,
        public array $distanceBands,
        public array $durationBands,
        public array $checkInBands,
        public array $dailyFactorBands,
    ) {
    }
}
