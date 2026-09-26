<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\Cpf;

it('creates a valid cpf from string', function (string $input, string $expected) {
    $result = Cpf::fromString($input);

    expect($result->isValid())->toBeTrue();

    $cpf = $result->value();

    expect($cpf->value())->toBe($expected);
})->with([
    'plain digits' => ['52998224725', '52998224725'],
    'formatted' => ['529.982.247-25', '52998224725'],
]);

it('rejects invalid cpf', function (string $input) {
    $result = Cpf::fromString($input);

    expect($result->isInvalid())->toBeTrue();
})->with([
    'wrong length' => '1234567890',
    'all same digits' => '11111111111',
    'invalid check digits' => '52998224726',
    'empty string' => '',
]);

it('reports invalid cpf with code and field', function () {
    $result = Cpf::fromString('1234567890');

    expect($result->isInvalid())->toBeTrue();

    $error = $result->errors()[0];

    expect($error->code)->toBe('CPF_INVALID')
        ->and($error->field)->toBe('cpf');
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
