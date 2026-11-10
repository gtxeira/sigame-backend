<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\GetGamificationRules;

/**
 * Catálogo completo das regras de gamificação da versão vigente.
 */
final readonly class GamificationRulesView
{
    /**
     * @param list<XpSourceView>    $xpSources
     * @param list<StreakStateView> $streakStates
     */
    public function __construct(
        public string $ruleVersion,
        public string $timezone,
        public LevelsRulesView $levels,
        public array $xpSources,
        public ActivityXpRulesView $activity,
        public WeeklyRulesView $weekly,
        public DecayRulesView $decay,
        public array $streakStates,
    ) {
    }
}
