<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Streak\Service;

use App\Gamification\Domain\Streak\ValueObject\WeeklyStreakState;

/**
 * Calcula a ofensiva semanal a partir das datas de atividades válidas.
 *
 * A semana é considerada válida com pelo menos uma atividade. A meta de duas
 * atividades rende o bônus maior, mas não é necessária para manter o foguinho.
 */
final readonly class StreakCalculator
{
    public function __construct(
        private StreakCalendar $calendar,
    ) {
    }

    /**
     * @param list<string> $activityDates datas Y-m-d
     */
    public function current(array $activityDates, string $today): int
    {
        $weeks = $this->weekSet($activityDates);
        $week = $this->calendar->weekKeyForDate($today);

        if (!isset($weeks[$week])) {
            $week = $this->calendar->addDays($week, -7);
        }

        $streak = 0;

        while (isset($weeks[$week])) {
            ++$streak;
            $week = $this->calendar->addDays($week, -7);
        }

        return $streak;
    }

    /**
     * @param list<string> $activityDates datas Y-m-d
     */
    public function best(array $activityDates): int
    {
        $weeks = array_keys($this->weekSet($activityDates));

        if ([] === $weeks) {
            return 0;
        }

        sort($weeks);

        $best = 1;
        $current = 1;

        for ($index = 1, $count = count($weeks); $index < $count; ++$index) {
            $expected = $this->calendar->addDays($weeks[$index - 1], 7);

            if ($weeks[$index] === $expected) {
                ++$current;
                $best = max($best, $current);

                continue;
            }

            $current = 1;
        }

        return $best;
    }

    /**
     * @param list<string> $activityDates datas Y-m-d
     */
    public function weeklyCount(array $activityDates, string $weekKey): int
    {
        $count = 0;

        foreach ($activityDates as $date) {
            if ($this->calendar->weekKeyForDate($date) === $weekKey) {
                ++$count;
            }
        }

        return $count;
    }

    public function state(int $weeklyCount, int $currentStreak): WeeklyStreakState
    {
        if ($weeklyCount >= 2) {
            return WeeklyStreakState::COMPLETE;
        }

        if (1 === $weeklyCount) {
            return WeeklyStreakState::LIT;
        }

        return $currentStreak > 0 ? WeeklyStreakState::AT_RISK : WeeklyStreakState::UNLIT;
    }

    /**
     * @param list<string> $activityDates
     *
     * @return array<string, true>
     */
    private function weekSet(array $activityDates): array
    {
        $weeks = [];

        foreach ($activityDates as $date) {
            $weeks[$this->calendar->weekKeyForDate($date)] = true;
        }

        return $weeks;
    }
}
