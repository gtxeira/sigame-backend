<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\BirthDate;
use App\Shared\Domain\ValueObject\Instant;

function birthDateInstant(string $datetime): Instant
{
    return Instant::fromString($datetime)->value();
}

it('creates a birth date from the y-m-d format', function () {
    $result = BirthDate::fromString('1990-05-12');

    expect($result->isValid())->toBeTrue()
        ->and($result->value()->value())->toBe('1990-05-12');
});

it('rejects an empty birth date', function () {
    $result = BirthDate::fromString('  ');

    expect($result->isInvalid())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('FIELD_REQUIRED')
        ->and($result->errors()[0]->field)->toBe('birthDate');
});

it('rejects malformed or impossible dates', function (string $input) {
    $result = BirthDate::fromString($input);

    expect($result->isInvalid())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('INVALID_FORMAT');
})->with([
    'empty-ish' => '12/05/1990',
    'missing day' => '1990-05',
    'impossible day' => '1990-02-30',
    'letters' => '1990-05-ab',
]);

it('rejects a birth date in the future', function () {
    $now = birthDateInstant('2026-11-11T12:00:00-03:00');

    $result = BirthDate::fromString('2026-11-12', now: $now);

    expect($result->isInvalid())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('NUMBER_OUT_OF_RANGE');
});

it('accepts today as the birth date', function () {
    $now = birthDateInstant('2026-11-11T12:00:00-03:00');

    $result = BirthDate::fromString('2026-11-11', now: $now);

    expect($result->isValid())->toBeTrue();
});

it('rejects dates before 1900', function () {
    $result = BirthDate::fromString('1899-12-31');

    expect($result->isInvalid())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('NUMBER_OUT_OF_RANGE');
});

it('calculates age on the birthday and on the day before', function () {
    $birthDate = BirthDate::fromString('2008-11-11')->value();

    expect($birthDate->ageAt(birthDateInstant('2026-11-10T23:59:59-03:00')))->toBe(17)
        ->and($birthDate->ageAt(birthDateInstant('2026-11-11T00:00:00-03:00')))->toBe(18);
});

it('calculates age in the sao paulo timezone', function () {
    $birthDate = BirthDate::fromString('2008-11-11')->value();

    // 2026-11-12T01:00:00Z == 2026-11-11T22:00:00-03:00: ainda é dia 11 no Brasil.
    expect($birthDate->ageAt(birthDateInstant('2026-11-12T01:00:00Z')))->toBe(18);
});

it('turns 18 on march first when born on february 29', function () {
    $birthDate = BirthDate::fromString('2008-02-29')->value();

    expect($birthDate->ageAt(birthDateInstant('2026-02-28T12:00:00-03:00')))->toBe(17)
        ->and($birthDate->ageAt(birthDateInstant('2026-03-01T00:00:00-03:00')))->toBe(18);
});

it('reports whether a minimum age was reached', function () {
    $birthDate = BirthDate::fromString('2008-11-11')->value();

    expect($birthDate->hasReached(18, birthDateInstant('2026-11-10T12:00:00-03:00')))->toBeFalse()
        ->and($birthDate->hasReached(18, birthDateInstant('2026-11-11T12:00:00-03:00')))->toBeTrue();
});

it('compares birth dates by value', function () {
    $first = BirthDate::fromString('1990-05-12')->value();
    $second = BirthDate::fromString('1990-05-12')->value();
    $third = BirthDate::fromString('1990-05-13')->value();

    expect($first->equals($second))->toBeTrue()
        ->and($first->equals($third))->toBeFalse()
        ->and((string) $first)->toBe('1990-05-12');
});
