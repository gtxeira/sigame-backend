<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\Height;

it('creates a valid height from centimeters', function () {
    $result = Height::fromCentimeters(178);

    expect($result->isValid())->toBeTrue();

    $height = $result->value();

    expect($height->centimeters())->toBe(178)
        ->and($height->meters())->toBe(1.78);
});

it('accepts the minimum and maximum heights', function (int $centimeters) {
    $result = Height::fromCentimeters($centimeters);

    expect($result->isValid())->toBeTrue()
        ->and($result->value()->centimeters())->toBe($centimeters);
})->with([
    Height::MIN_CENTIMETERS,
    Height::MAX_CENTIMETERS,
]);

it('rejects heights outside the allowed range', function (int $centimeters) {
    $result = Height::fromCentimeters($centimeters);

    expect($result->isInvalid())->toBeTrue();

    $error = $result->errors()[0];

    expect($error->code)->toBe('USER_HEIGHT_INVALID')
        ->and($error->field)->toBe('heightCm');
})->with([
    Height::MIN_CENTIMETERS - 1,
    Height::MAX_CENTIMETERS + 1,
]);

it('compares heights by value', function () {
    $height = Height::fromCentimeters(178)->value();
    $sameHeight = Height::fromCentimeters(178)->value();
    $otherHeight = Height::fromCentimeters(180)->value();

    expect($height->equals($sameHeight))->toBeTrue()
        ->and($height->equals($otherHeight))->toBeFalse();
});
