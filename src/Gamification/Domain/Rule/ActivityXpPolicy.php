<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Rule;

use App\Gamification\Domain\Streak\Service\StreakCalendar;
use App\Shared\Application\Activity\ActivityXpContext;
use App\Shared\Domain\ValueObject\Modality;

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
    ) {
    }

    public function calculate(ActivityXpContext $context, int $dailySequence): int
    {
        $xp = $this->base($context->modality)
            + $this->distanceBonus($context->modality, $context->distanceMeters)
            + $this->durationBonus($context->durationMinutes)
            + $this->groupBonus($context->checkedInParticipants);

        if ($this->calendar->isWeekend($context->startsAt)) {
            $xp = intdiv($xp * GamificationRules::WEEKEND_MULTIPLIER_PERCENT, 100);
        }

        $xp = intdiv($xp * $this->dailyFactorPercent($dailySequence), 100);

        return min($xp, GamificationRules::MAX_XP_PER_ACTIVITY);
    }

    private function base(Modality $modality): int
    {
        return match ($modality) {
            Modality::WALKING => 20,
            Modality::RUNNING => 25,
            Modality::CYCLING => 30,
        };
    }

    private function distanceBonus(Modality $modality, int $distanceMeters): int
    {
        $km = $distanceMeters / 1000;

        return match ($modality) {
            Modality::WALKING => match (true) {
                $km <= 2 => 0,
                $km <= 5 => 10,
                $km <= 10 => 15,
                default => 20,
            },
            Modality::RUNNING => match (true) {
                $km <= 3 => 0,
                $km <= 6 => 10,
                $km <= 10 => 15,
                default => 20,
            },
            Modality::CYCLING => match (true) {
                $km <= 10 => 0,
                $km <= 20 => 10,
                $km <= 40 => 15,
                default => 20,
            },
        };
    }

    private function durationBonus(?int $durationMinutes): int
    {
        if (null === $durationMinutes || $durationMinutes < 30) {
            return 0;
        }

        return $durationMinutes < 60 ? 5 : 10;
    }

    private function groupBonus(int $checkedInParticipants): int
    {
        if ($checkedInParticipants >= 5) {
            return 15;
        }

        return $checkedInParticipants >= 3 ? 10 : 0;
    }

    private function dailyFactorPercent(int $dailySequence): int
    {
        if ($dailySequence <= GamificationRules::FULL_XP_ACTIVITIES_PER_DAY) {
            return 100;
        }

        if (GamificationRules::FULL_XP_ACTIVITIES_PER_DAY + 1 === $dailySequence) {
            return GamificationRules::FOURTH_ACTIVITY_PERCENT;
        }

        return 0;
    }
}
