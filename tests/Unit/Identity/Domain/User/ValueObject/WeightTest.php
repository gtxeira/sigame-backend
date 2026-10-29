<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\Weight;

it('creates a valid weight from kilograms', function () {
    $result = Weight::fromKilograms(75.5);

    expect($result->isValid())->toBeTrue()
        ->and($result->value()->kilograms())->toBe(75.5);
});

it('accepts the minimum and maximum weights', function (float $kilograms) {
    $result = Weight::fromKilograms($kilograms);

    expect($result->isValid())->toBeTrue()
        ->and($result->value()->kilograms())->toBe($kilograms);
})->with([
    Weight::MIN_KILOGRAMS,
    Weight::MAX_KILOGRAMS,
]);

it('rejects weights outside the allowed range', function (float $kilograms) {
    $result = Weight::fromKilograms($kilograms);

    expect($result->isInvalid())->toBeTrue();

    $error = $result->errors()[0];

    expect($error->code)->toBe('USER_WEIGHT_INVALID')
        ->and($error->field)->toBe('weightKg');
})->with([
    Weight::MIN_KILOGRAMS - 0.1,
    Weight::MAX_KILOGRAMS + 0.1,
]);

it('rejects non-finite weights', function (float $kilograms) {
    $result = Weight::fromKilograms($kilograms);

    expect($result->isInvalid())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('USER_WEIGHT_INVALID');
})->with([
    'nan' => NAN,
    'positive infinity' => INF,
    'negative infinity' => -INF,
]);

it('compares weights by value', function () {
    $weight = Weight::fromKilograms(75.5)->value();
    $sameWeight = Weight::fromKilograms(75.5)->value();
    $otherWeight = Weight::fromKilograms(80.0)->value();

    expect($weight->equals($sameWeight))->toBeTrue()
        ->and($weight->equals($otherWeight))->toBeFalse();
});
