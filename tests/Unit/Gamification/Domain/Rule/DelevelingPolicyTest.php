<?php

declare(strict_types=1);

use App\Gamification\Domain\Rule\DelevelingPolicy;
use App\Gamification\Domain\Rule\LevelCurve;
use App\Shared\Domain\ValueObject\Instant;

function delevelingPolicy(): DelevelingPolicy
{
    return new DelevelingPolicy(new LevelCurve());
}

it('does not decay before the grace period', function () {
    $last = Instant::fromString('2026-11-01T12:00:00+00:00')->value();
    $now = Instant::fromString('2026-11-08T12:00:00+00:00')->value();

    expect(delevelingPolicy()->completePeriods($last, $now))->toBe(0);
});

it('counts complete periods after the grace period', function () {
    $last = Instant::fromString('2026-11-01T12:00:00+00:00')->value();

    expect(delevelingPolicy()->completePeriods(
        $last,
        Instant::fromString('2026-11-10T12:00:00+00:00')->value(),
    ))->toBe(1)
        ->and(delevelingPolicy()->completePeriods(
            $last,
            Instant::fromString('2026-11-12T12:00:00+00:00')->value(),
        ))->toBe(2);
});

it('applies two percent of the current bar per period', function () {
    $result = delevelingPolicy()->apply(1250, 5, 5);

    expect($result->xpBalance)->toBe(1200)
        ->and($result->level)->toBe(5)
        ->and($result->xpLost)->toBe(50);
});

it('lands at 98 percent of the previous level when crossing zero', function () {
    $result = delevelingPolicy()->apply(1000, 5, 1);

    expect($result->xpBalance)->toBe(992)
        ->and($result->level)->toBe(4)
        ->and($result->levelsLost)->toBe(1);
});

it('never drops below level 2', function () {
    $result = delevelingPolicy()->apply(100, 2, 10);

    expect($result->xpBalance)->toBe(100)
        ->and($result->level)->toBe(2)
        ->and($result->xpLost)->toBe(0);
});
