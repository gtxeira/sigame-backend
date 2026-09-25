<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\Cpf;

it('creates a valid cpf from string', function () {
    $result = Cpf::fromString('52998224725');

    expect($result->isValid())->toBeTrue()
        ->and($result->value()->value())->toBe('52998224725');
});

it('normalizes cpf by removing non-digit characters', function () {
    $result = Cpf::fromString('529.982.247-25');

    expect($result->isValid())->toBeTrue()
        ->and($result->value()->value())->toBe('52998224725');
});

it('rejects cpf with wrong length', function () {
    $result = Cpf::fromString('1234567890');

    expect($result->isInvalid())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('CPF_INVALID')
        ->and($result->errors()[0]->field)->toBe('cpf');
});

it('rejects cpf with all same digits', function () {
    $result = Cpf::fromString('11111111111');

    expect($result->isInvalid())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('CPF_INVALID');
});

it('rejects cpf with invalid check digits', function () {
    $result = Cpf::fromString('52998224726');

    expect($result->isInvalid())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('CPF_INVALID');
});

it('rejects empty string', function () {
    $result = Cpf::fromString('');

    expect($result->isInvalid())->toBeTrue();
});

it('formats cpf correctly', function () {
    $cpf = Cpf::fromString('52998224725')->value();

    expect($cpf->formatted())->toBe('529.982.247-25');
});

it('compares cpfs by value', function () {
    $cpf1 = Cpf::fromString('52998224725')->value();
    $cpf2 = Cpf::fromString('52998224725')->value();

    expect($cpf1->equals($cpf2))->toBeTrue();
});

it('distinguishes different cpfs', function () {
    $cpf1 = Cpf::fromString('52998224725')->value();
    $cpf2 = Cpf::fromString('47705122014')->value();

    expect($cpf1->equals($cpf2))->toBeFalse();
});

it('converts to string', function () {
    $cpf = Cpf::fromString('52998224725')->value();

    expect((string) $cpf)->toBe('52998224725');
});
