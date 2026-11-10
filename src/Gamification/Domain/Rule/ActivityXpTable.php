<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Rule;

use App\Shared\Domain\ValueObject\Modality;

/**
 * Tabelas de XP de atividade.
 *
 * Centraliza as faixas usadas no cálculo e no catálogo público de regras,
 * para que o valor exibido pelo cliente e o valor concedido não divirjam.
 * As faixas são ordenadas e inclusivas no limite superior; `null` significa
 * "acima da faixa anterior".
 */
final class ActivityXpTable
{
    /**
     * @return array<string, int>
     */
    public function baseXpByModality(): array
    {
        $base = [];

        foreach (Modality::cases() as $modality) {
            $base[$modality->value] = $this->baseXp($modality);
        }

        return $base;
    }

    public function baseXp(Modality $modality): int
    {
        return match ($modality) {
            Modality::WALKING => 20,
            Modality::RUNNING => 25,
            Modality::CYCLING => 30,
        };
    }

    /**
     * @return array<string, list<array{upToMeters: int|null, bonus: int}>>
     */
    public function distanceBandsByModality(): array
    {
        $bands = [];

        foreach (Modality::cases() as $modality) {
            $bands[$modality->value] = $this->distanceBands($modality);
        }

        return $bands;
    }

    public function distanceBonus(Modality $modality, int $distanceMeters): int
    {
        foreach ($this->distanceBands($modality) as $band) {
            if (null === $band['upToMeters'] || $distanceMeters <= $band['upToMeters']) {
                return $band['bonus'];
            }
        }

        return 0;
    }

    /**
     * @return list<array{minMinutes: int, maxMinutes: int|null, bonus: int}>
     */
    public function durationBands(): array
    {
        return [
            ['minMinutes' => 0, 'maxMinutes' => 29, 'bonus' => 0],
            ['minMinutes' => 30, 'maxMinutes' => 59, 'bonus' => 5],
            ['minMinutes' => 60, 'maxMinutes' => null, 'bonus' => 10],
        ];
    }

    public function durationBonus(?int $durationMinutes): int
    {
        if (null === $durationMinutes) {
            return 0;
        }

        foreach ($this->durationBands() as $band) {
            $withinMax = null === $band['maxMinutes'] || $durationMinutes <= $band['maxMinutes'];

            if ($durationMinutes >= $band['minMinutes'] && $withinMax) {
                return $band['bonus'];
            }
        }

        return 0;
    }

    /**
     * @return list<array{minCheckIns: int, maxCheckIns: int|null, bonus: int}>
     */
    public function checkInBands(): array
    {
        return [
            ['minCheckIns' => 2, 'maxCheckIns' => 2, 'bonus' => 0],
            ['minCheckIns' => 3, 'maxCheckIns' => 4, 'bonus' => 10],
            ['minCheckIns' => 5, 'maxCheckIns' => null, 'bonus' => 15],
        ];
    }

    public function groupBonus(int $checkedInParticipants): int
    {
        foreach ($this->checkInBands() as $band) {
            $withinMax = null === $band['maxCheckIns'] || $checkedInParticipants <= $band['maxCheckIns'];

            if ($checkedInParticipants >= $band['minCheckIns'] && $withinMax) {
                return $band['bonus'];
            }
        }

        return 0;
    }

    /**
     * @return list<array{minActivities: int, maxActivities: int|null, percent: int}>
     */
    public function dailyFactorBands(): array
    {
        return [
            [
                'minActivities' => 1,
                'maxActivities' => GamificationRules::FULL_XP_ACTIVITIES_PER_DAY,
                'percent' => 100,
            ],
            [
                'minActivities' => GamificationRules::FULL_XP_ACTIVITIES_PER_DAY + 1,
                'maxActivities' => GamificationRules::FULL_XP_ACTIVITIES_PER_DAY + 1,
                'percent' => GamificationRules::FOURTH_ACTIVITY_PERCENT,
            ],
            [
                'minActivities' => GamificationRules::FULL_XP_ACTIVITIES_PER_DAY + 2,
                'maxActivities' => null,
                'percent' => 0,
            ],
        ];
    }

    public function dailyFactorPercent(int $dailySequence): int
    {
        if ($dailySequence < 1) {
            return 100;
        }

        foreach ($this->dailyFactorBands() as $band) {
            $withinMax = null === $band['maxActivities'] || $dailySequence <= $band['maxActivities'];

            if ($dailySequence >= $band['minActivities'] && $withinMax) {
                return $band['percent'];
            }
        }

        return 0;
    }

    /**
     * @return list<array{upToMeters: int|null, bonus: int}>
     */
    private function distanceBands(Modality $modality): array
    {
        return match ($modality) {
            Modality::WALKING => [
                ['upToMeters' => 2_000, 'bonus' => 0],
                ['upToMeters' => 5_000, 'bonus' => 10],
                ['upToMeters' => 10_000, 'bonus' => 15],
                ['upToMeters' => null, 'bonus' => 20],
            ],
            Modality::RUNNING => [
                ['upToMeters' => 3_000, 'bonus' => 0],
                ['upToMeters' => 6_000, 'bonus' => 10],
                ['upToMeters' => 10_000, 'bonus' => 15],
                ['upToMeters' => null, 'bonus' => 20],
            ],
            Modality::CYCLING => [
                ['upToMeters' => 10_000, 'bonus' => 0],
                ['upToMeters' => 20_000, 'bonus' => 10],
                ['upToMeters' => 40_000, 'bonus' => 15],
                ['upToMeters' => null, 'bonus' => 20],
            ],
        };
    }
}
