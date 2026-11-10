<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Rule;

use App\Gamification\Domain\Streak\Service\StreakCalendar;
use App\Shared\Application\Activity\ActivityXpContext;

/**
 * Calcula o XP de uma atividade validada.
 *
 * A ordem é: base + distância + duração + grupo; fim de semana; fator diário;
 * teto de 100 XP. O bônus de grupo usa check-ins para que todos os
 * participantes recebam o mesmo valor.
 */
final readonly class ActivityXpPolicy
{
    public function __construct(
        private StreakCalendar $calendar,
        private ActivityXpTable $table,
    ) {
    }

    public function calculate(ActivityXpContext $context, int $dailySequence): int
    {
        $xp = $this->table->baseXp($context->modality)
            + $this->table->distanceBonus($context->modality, $context->distanceMeters)
            + $this->table->durationBonus($context->durationMinutes)
            + $this->table->groupBonus($context->checkedInParticipants);

        if ($this->calendar->isWeekend($context->startsAt)) {
            $xp = intdiv($xp * GamificationRules::WEEKEND_MULTIPLIER_PERCENT, 100);
        }

        $xp = intdiv($xp * $this->table->dailyFactorPercent($dailySequence), 100);

        return min($xp, GamificationRules::MAX_XP_PER_ACTIVITY);
    }
}
