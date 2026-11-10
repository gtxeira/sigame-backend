<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\GetGamificationRules;

use App\Gamification\Domain\Rule\ActivityXpTable;
use App\Gamification\Domain\Rule\GamificationRules;
use App\Gamification\Domain\Rule\LevelCurve;
use App\Gamification\Domain\Rule\WeeklyXpPolicy;
use App\Gamification\Domain\Streak\ValueObject\WeeklyStreakState;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Shared\Domain\ValueObject\Modality;

/**
 * Monta o catálogo de regras a partir das mesmas fontes usadas no cálculo.
 */
final readonly class GetGamificationRulesHandler
{
    public function __construct(
        private LevelCurve $levelCurve,
        private ActivityXpTable $activityXpTable,
        private WeeklyXpPolicy $weeklyXpPolicy,
    ) {
    }

    public function __invoke(): GamificationRulesView
    {
        return new GamificationRulesView(
            ruleVersion: GamificationRules::VERSION,
            timezone: GamificationRules::TIMEZONE,
            levels: $this->levels(),
            xpSources: $this->xpSources(),
            activity: $this->activity(),
            weekly: $this->weekly(),
            decay: $this->decay(),
            streakStates: $this->streakStates(),
        );
    }

    private function levels(): LevelsRulesView
    {
        $curve = [];

        for ($level = GamificationRules::MIN_LEVEL; $level <= GamificationRules::MAX_LEVEL; ++$level) {
            $nextLevelXp = $this->levelCurve->nextLevelXp($level);

            $curve[] = [
                'level' => $level,
                'thresholdXp' => $this->levelCurve->threshold($level),
                'xpForNextLevel' => null === $nextLevelXp
                    ? null
                    : $nextLevelXp - $this->levelCurve->threshold($level),
            ];
        }

        return new LevelsRulesView(
            minLevel: GamificationRules::MIN_LEVEL,
            maxLevel: GamificationRules::MAX_LEVEL,
            curve: $curve,
        );
    }

    /**
     * @return list<XpSourceView>
     */
    private function xpSources(): array
    {
        return [
            new XpSourceView(
                kind: XpKind::ONBOARDING->value,
                label: XpKind::ONBOARDING->label(),
                description: 'Concedido uma única vez na conclusão do onboarding.',
                amount: GamificationRules::ONBOARDING_XP,
                minAmount: null,
                maxAmount: null,
            ),
            new XpSourceView(
                kind: XpKind::ACTIVITY->value,
                label: XpKind::ACTIVITY->label(),
                description: 'Calculado pela fórmula de atividade e limitado a 100 XP por atividade.',
                amount: null,
                minAmount: 0,
                maxAmount: GamificationRules::MAX_XP_PER_ACTIVITY,
            ),
            new XpSourceView(
                kind: XpKind::WEEKLY_START->value,
                label: XpKind::WEEKLY_START->label(),
                description: 'Primeira atividade válida da semana.',
                amount: GamificationRules::WEEKLY_START_XP,
                minAmount: null,
                maxAmount: null,
            ),
            new XpSourceView(
                kind: XpKind::WEEKLY_GOAL->value,
                label: XpKind::WEEKLY_GOAL->label(),
                description: 'Segunda atividade válida da semana; cresce com a sequência.',
                amount: null,
                minAmount: GamificationRules::WEEKLY_GOAL_BASE_XP,
                maxAmount: GamificationRules::WEEKLY_GOAL_MAX_XP,
            ),
        ];
    }

    private function activity(): ActivityXpRulesView
    {
        $baseXpByModality = [];

        foreach (Modality::cases() as $modality) {
            $baseXpByModality[] = [
                'modality' => $modality->value,
                'xp' => $this->activityXpTable->baseXp($modality),
            ];
        }

        $distanceBands = [];

        foreach ($this->activityXpTable->distanceBandsByModality() as $modality => $bands) {
            $distanceBands[] = [
                'modality' => $modality,
                'bands' => $bands,
            ];
        }

        return new ActivityXpRulesView(
            eligibility: new ActivityEligibilityView(
                minimumCheckIns: GamificationRules::MIN_CHECKED_IN_FOR_XP,
                requiresFinishedActivity: true,
                requiresValidatedAttendance: true,
                requiresXpEligibleEvent: true,
            ),
            maxXpPerActivity: GamificationRules::MAX_XP_PER_ACTIVITY,
            weekendMultiplierPercent: GamificationRules::WEEKEND_MULTIPLIER_PERCENT,
            durationBonusWhenMissing: 0,
            baseXpByModality: $baseXpByModality,
            distanceBands: $distanceBands,
            durationBands: $this->activityXpTable->durationBands(),
            checkInBands: $this->activityXpTable->checkInBands(),
            dailyFactorBands: $this->activityXpTable->dailyFactorBands(),
        );
    }

    private function weekly(): WeeklyRulesView
    {
        $progression = $this->weeklyXpPolicy->goalProgression();
        $capFromStreak = $progression[count($progression) - 1]['streak'];

        foreach ($progression as $row) {
            if (GamificationRules::WEEKLY_GOAL_MAX_XP === $row['amount']) {
                $capFromStreak = $row['streak'];

                break;
            }
        }

        return new WeeklyRulesView(
            goalActivities: GamificationRules::WEEKLY_GOAL_ACTIVITIES,
            startXp: $this->weeklyXpPolicy->start(),
            goalBaseXp: GamificationRules::WEEKLY_GOAL_BASE_XP,
            goalStepXp: GamificationRules::WEEKLY_GOAL_STEP_XP,
            goalMaxXp: GamificationRules::WEEKLY_GOAL_MAX_XP,
            goalBonusByStreak: $progression,
            goalBonusCapFromStreak: $capFromStreak,
            weekStartsOn: 'monday',
        );
    }

    private function decay(): DecayRulesView
    {
        return new DecayRulesView(
            graceDays: GamificationRules::DECAY_GRACE_DAYS,
            periodDays: GamificationRules::DECAY_PERIOD_DAYS,
            barPercentPerPeriod: GamificationRules::DECAY_PERCENT,
            floorLevel: GamificationRules::DECAY_FLOOR_LEVEL,
            levelDropBarPercent: GamificationRules::DECAY_DROP_BAR_PERCENT,
            usesValidationInstant: true,
        );
    }

    /**
     * @return list<StreakStateView>
     */
    private function streakStates(): array
    {
        $states = [];

        foreach (WeeklyStreakState::cases() as $state) {
            $states[] = new StreakStateView(
                value: $state->value,
                label: $state->label(),
                description: $this->streakStateDescription($state),
            );
        }

        return $states;
    }

    private function streakStateDescription(WeeklyStreakState $state): string
    {
        return match ($state) {
            WeeklyStreakState::UNLIT => 'O usuário nunca teve uma semana válida ou a última semana terminou sem atividades.',
            WeeklyStreakState::LIT => 'A semana atual já tem pelo menos uma atividade.',
            WeeklyStreakState::AT_RISK => 'A semana atual ainda não tem atividade e a anterior estava acesa.',
            WeeklyStreakState::COMPLETE => 'A semana atual já tem duas ou mais atividades.',
        };
    }
}
