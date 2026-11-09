<?php

declare(strict_types=1);

use App\Activities\Domain\Activity\Policy\ActivityPolicy;
use App\Activities\Domain\Activity\ValueObject\ActivityWindow;
use App\Shared\Domain\ValueObject\Instant;

function activityWindowAt(string $start, ?int $duration = 60): ActivityWindow
{
    return ActivityWindow::fromStart(Instant::fromDateTime(new DateTimeImmutable($start)), $duration)->value();
}

it('detects overlapping windows and accepts adjacent ones', function () {
    $first = activityWindowAt('2026-11-15 10:00:00', 60);
    $overlapping = activityWindowAt('2026-11-15 10:30:00', 60);
    $adjacent = activityWindowAt('2026-11-15 11:00:00', 60);

    expect($first->overlaps($overlapping))->toBeTrue()
        ->and($first->overlaps($adjacent))->toBeFalse();
});

it('uses the default conflict window when duration is omitted', function () {
    $window = activityWindowAt('2026-11-15 10:00:00', null);

    expect($window->estimatedDurationMinutes())->toBeNull()
        ->and($window->plannedDurationMinutes())->toBe(ActivityPolicy::DEFAULT_CONFLICT_WINDOW_MINUTES)
        ->and($window->endsAt()->timestamp())->toBe($window->startsAt()->timestamp() + 7200);
});

it('calculates the automatic close time with a proportional tolerance', function () {
    $instant = Instant::fromDateTime(new DateTimeImmutable('2026-11-15 10:00:00'));

    expect(ActivityPolicy::autoCloseAt($instant, 15)->timestamp())->toBe($instant->timestamp() + (30 * 60))
        ->and(ActivityPolicy::autoCloseAt($instant, 60)->timestamp())->toBe($instant->timestamp() + (90 * 60))
        ->and(ActivityPolicy::autoCloseAt($instant, 480)->timestamp())->toBe($instant->timestamp() + (720 * 60));
});

it('uses twelve hours when duration is omitted for automatic close', function () {
    $instant = Instant::fromDateTime(new DateTimeImmutable('2026-11-15 10:00:00'));

    expect(ActivityPolicy::autoCloseAt($instant, null)->timestamp())
        ->toBe($instant->timestamp() + (12 * 3600));
});
