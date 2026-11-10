<?php

declare(strict_types=1);

use App\Gamification\Domain\Streak\Service\StreakCalculator;
use App\Gamification\Domain\Streak\Service\StreakCalendar;
use App\Gamification\Domain\Streak\ValueObject\WeeklyStreakState;

function streakCalculator(): StreakCalculator
{
    return new StreakCalculator(new StreakCalendar());
}

it('counts consecutive weeks with activity', function () {
    $dates = ['2026-11-02', '2026-11-09', '2026-11-16'];

    expect(streakCalculator()->current($dates, '2026-11-18'))->toBe(3)
        ->and(streakCalculator()->best($dates))->toBe(3);
});

it('keeps the streak alive when the current week has no activity yet', function () {
    $dates = ['2026-11-02', '2026-11-09'];

    expect(streakCalculator()->current($dates, '2026-11-18'))->toBe(2)
        ->and(streakCalculator()->state(0, 2))->toBe(WeeklyStreakState::AT_RISK);
});

it('resets when the previous week had no activity', function () {
    $dates = ['2026-11-02', '2026-11-16'];

    expect(streakCalculator()->current($dates, '2026-11-18'))->toBe(1);
});

it('counts activities per week and reports the state', function () {
    $dates = ['2026-11-09', '2026-11-10'];

    expect(streakCalculator()->weeklyCount($dates, '2026-11-09'))->toBe(2)
        ->and(streakCalculator()->state(2, 1))->toBe(WeeklyStreakState::COMPLETE)
        ->and(streakCalculator()->state(1, 1))->toBe(WeeklyStreakState::LIT)
        ->and(streakCalculator()->state(0, 0))->toBe(WeeklyStreakState::UNLIT);
});
