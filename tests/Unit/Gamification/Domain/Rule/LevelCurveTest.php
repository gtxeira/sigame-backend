<?php

declare(strict_types=1);

use App\Gamification\Domain\Rule\LevelCurve;

it('uses the triangular level curve', function () {
    $curve = new LevelCurve();

    expect($curve->threshold(1))->toBe(0)
        ->and($curve->threshold(2))->toBe(100)
        ->and($curve->threshold(3))->toBe(300)
        ->and($curve->threshold(4))->toBe(600)
        ->and($curve->threshold(5))->toBe(1000)
        ->and($curve->threshold(10))->toBe(4500);
});

it('derives the level from the xp balance', function () {
    $curve = new LevelCurve();

    expect($curve->levelFor(0))->toBe(1)
        ->and($curve->levelFor(99))->toBe(1)
        ->and($curve->levelFor(100))->toBe(2)
        ->and($curve->levelFor(299))->toBe(2)
        ->and($curve->levelFor(300))->toBe(3)
        ->and($curve->levelFor(9999))->toBe(10);
});

it('calculates the bar span and percentage', function () {
    $curve = new LevelCurve();

    expect($curve->barSpan(1))->toBe(100)
        ->and($curve->barSpan(5))->toBe(500)
        ->and($curve->barSpan(10))->toBe(900)
        ->and($curve->barPercent(5, 1250))->toBe(0.5)
        ->and($curve->barPercent(5, 1000))->toBe(0.0)
        ->and($curve->barPercent(5, 1500))->toBe(1.0);
});
