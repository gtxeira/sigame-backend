<?php

declare(strict_types=1);

use App\Gamification\Domain\Rule\ActivityXpTable;
use App\Shared\Domain\ValueObject\Modality;

it('exposes the base xp by modality', function () {
    expect((new ActivityXpTable())->baseXpByModality())->toBe([
        'caminhada' => 20,
        'corrida' => 25,
        'ciclismo' => 30,
    ]);
});

it('applies the distance bands by modality', function (Modality $modality, int $meters, int $expected) {
    expect((new ActivityXpTable())->distanceBonus($modality, $meters))->toBe($expected);
})->with([
    'walking up to 2 km' => [Modality::WALKING, 2000, 0],
    'walking above 2 km' => [Modality::WALKING, 2001, 10],
    'walking up to 5 km' => [Modality::WALKING, 5000, 10],
    'walking above 5 km' => [Modality::WALKING, 5001, 15],
    'walking up to 10 km' => [Modality::WALKING, 10000, 15],
    'walking above 10 km' => [Modality::WALKING, 10001, 20],
    'running up to 3 km' => [Modality::RUNNING, 3000, 0],
    'running above 3 km' => [Modality::RUNNING, 3001, 10],
    'running above 6 km' => [Modality::RUNNING, 6001, 15],
    'running above 10 km' => [Modality::RUNNING, 10001, 20],
    'cycling up to 10 km' => [Modality::CYCLING, 10000, 0],
    'cycling above 10 km' => [Modality::CYCLING, 10001, 10],
    'cycling above 20 km' => [Modality::CYCLING, 20001, 15],
    'cycling above 40 km' => [Modality::CYCLING, 40001, 20],
]);

it('applies the duration bands', function (?int $minutes, int $expected) {
    expect((new ActivityXpTable())->durationBonus($minutes))->toBe($expected);
})->with([
    'not informed' => [null, 0],
    '29 minutes' => [29, 0],
    '30 minutes' => [30, 5],
    '59 minutes' => [59, 5],
    '60 minutes' => [60, 10],
]);

it('applies the group bonus by check-ins', function (int $checkIns, int $expected) {
    expect((new ActivityXpTable())->groupBonus($checkIns))->toBe($expected);
})->with([
    'two check-ins' => [2, 0],
    'three check-ins' => [3, 10],
    'four check-ins' => [4, 10],
    'five check-ins' => [5, 15],
]);

it('applies the daily factor by sequence', function (int $sequence, int $expected) {
    expect((new ActivityXpTable())->dailyFactorPercent($sequence))->toBe($expected);
})->with([
    'first activity' => [1, 100],
    'third activity' => [3, 100],
    'fourth activity' => [4, 25],
    'fifth activity' => [5, 0],
    'sixth activity' => [6, 0],
]);
