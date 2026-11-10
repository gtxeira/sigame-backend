<?php

declare(strict_types=1);

use App\Gamification\Domain\Rule\WeeklyXpPolicy;

it('grants the weekly start bonus', function () {
    expect((new WeeklyXpPolicy())->start())->toBe(10);
});

it('scales the weekly goal bonus with the streak', function () {
    $policy = new WeeklyXpPolicy();

    expect($policy->goal(1))->toBe(20)
        ->and($policy->goal(2))->toBe(25)
        ->and($policy->goal(3))->toBe(30)
        ->and($policy->goal(7))->toBe(50)
        ->and($policy->goal(30))->toBe(50);
});
