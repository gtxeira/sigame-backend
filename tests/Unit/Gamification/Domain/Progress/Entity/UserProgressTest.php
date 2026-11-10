<?php

declare(strict_types=1);

use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;

it('reconciles the materialized xp values', function () {
    $progress = UserProgress::create(PlayerId::generate());

    $progress->reconcile(250, 300);

    expect($progress->xpBalance())->toBe(250)
        ->and($progress->xpLifetime())->toBe(300);
});

it('rejects negative reconciled values', function () {
    $progress = UserProgress::create(PlayerId::generate());

    expect(fn () => $progress->reconcile(-1, 0))->toThrow(LogicException::class)
        ->and(fn () => $progress->reconcile(0, -1))->toThrow(LogicException::class);
});
