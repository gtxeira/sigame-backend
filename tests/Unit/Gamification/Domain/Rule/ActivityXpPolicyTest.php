<?php

declare(strict_types=1);

use App\Gamification\Domain\Rule\ActivityXpPolicy;
use App\Gamification\Domain\Rule\ActivityXpTable;
use App\Gamification\Domain\Streak\Service\StreakCalendar;
use App\Shared\Application\Activity\ActivityXpContext;
use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Domain\ValueObject\Modality;

function activityContext(
    Modality $modality,
    int $distanceMeters,
    ?int $durationMinutes,
    int $checkedIn = 2,
    string $startsAt = '2026-11-09T12:00:00+00:00',
): ActivityXpContext {
    return new ActivityXpContext(
        activityId: 'activity-id',
        groupId: 'group-id',
        modality: $modality,
        distanceMeters: $distanceMeters,
        durationMinutes: $durationMinutes,
        startsAt: Instant::fromString($startsAt)->value(),
        checkedInParticipants: $checkedIn,
    );
}

it('calculates walking xp on a weekday', function () {
    $policy = new ActivityXpPolicy(new StreakCalendar(), new ActivityXpTable());

    expect($policy->calculate(activityContext(Modality::WALKING, 3000, 45), 1))->toBe(35);
});

it('applies the weekend multiplier', function () {
    $policy = new ActivityXpPolicy(new StreakCalendar(), new ActivityXpTable());

    expect($policy->calculate(
        activityContext(Modality::WALKING, 3000, 45, startsAt: '2026-11-14T12:00:00+00:00'),
        1,
    ))->toBe(42);
});

it('calculates running and cycling xp', function () {
    $policy = new ActivityXpPolicy(new StreakCalendar(), new ActivityXpTable());

    expect($policy->calculate(activityContext(Modality::RUNNING, 5000, 30), 1))->toBe(40)
        ->and($policy->calculate(activityContext(Modality::CYCLING, 25000, 90, 3), 1))->toBe(65);
});

it('applies the daily activity factor', function () {
    $policy = new ActivityXpPolicy(new StreakCalendar(), new ActivityXpTable());

    expect($policy->calculate(activityContext(Modality::RUNNING, 5000, 30), 3))->toBe(40)
        ->and($policy->calculate(activityContext(Modality::RUNNING, 5000, 30), 4))->toBe(10)
        ->and($policy->calculate(activityContext(Modality::RUNNING, 5000, 30), 5))->toBe(0);
});

it('never exceeds the per-activity cap', function () {
    $policy = new ActivityXpPolicy(new StreakCalendar(), new ActivityXpTable());

    expect($policy->calculate(
        activityContext(Modality::CYCLING, 50000, 120, 5, '2026-11-14T12:00:00+00:00'),
        1,
    ))->toBeLessThanOrEqual(100);
});
