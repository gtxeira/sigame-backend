<?php

declare(strict_types=1);

use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\ErrorKind;
use App\Shared\Domain\Functional\ValidationResult;

it('creates a valid result with value', function () {
    $result = ValidationResult::valid('hello');

    expect($result->isValid())->toBeTrue()
        ->and($result->isInvalid())->toBeFalse()
        ->and($result->value())->toBe('hello')
        ->and($result->errors())->toBe([]);
});

it('creates an invalid result with errors', function () {
    $errors = [
        new DomainError(code: 'CPF_INVALID', message: 'CPF inválido.', kind: ErrorKind::Validation, field: 'cpf'),
    ];

    $result = ValidationResult::invalid($errors);

    expect($result->isValid())->toBeFalse()
        ->and($result->isInvalid())->toBeTrue()
        ->and($result->errors())->toHaveCount(1)
        ->and($result->errors()[0]->code)->toBe('CPF_INVALID');
});

it('throws logic exception when accessing value on invalid result', function () {
    $result = ValidationResult::invalid([
        new DomainError(code: 'ERROR', message: 'Erro.', kind: ErrorKind::Validation),
    ]);

    $result->value();
})->throws(LogicException::class);

it('combines multiple valid results', function () {
    $r1 = ValidationResult::valid('a');
    $r2 = ValidationResult::valid('b');
    $r3 = ValidationResult::valid('c');

    $combined = ValidationResult::combine($r1, $r2, $r3);

    expect($combined->isValid())->toBeTrue()
        ->and($combined->value())->toBe(['a', 'b', 'c']);
});

it('combines results with errors and accumulates all', function () {
    $r1 = ValidationResult::invalid([
        new DomainError(code: 'CPF_INVALID', message: 'CPF inválido.', kind: ErrorKind::Validation, field: 'cpf'),
    ]);
    $r2 = ValidationResult::valid('ok');
    $r3 = ValidationResult::invalid([
        new DomainError(code: 'EMAIL_INVALID', message: 'Email inválido.', kind: ErrorKind::Validation, field: 'email'),
    ]);

    $combined = ValidationResult::combine($r1, $r2, $r3);

    expect($combined->isInvalid())->toBeTrue()
        ->and($combined->errors())->toHaveCount(2)
        ->and($combined->errors()[0]->code)->toBe('CPF_INVALID')
        ->and($combined->errors()[1]->code)->toBe('EMAIL_INVALID');
});

it('combines all invalid results', function () {
    $r1 = ValidationResult::invalid([
        new DomainError(code: 'ERROR_1', message: 'Erro 1.', kind: ErrorKind::Validation),
    ]);
    $r2 = ValidationResult::invalid([
        new DomainError(code: 'ERROR_2', message: 'Erro 2.', kind: ErrorKind::Validation),
        new DomainError(code: 'ERROR_3', message: 'Erro 3.', kind: ErrorKind::Validation),
    ]);

    $combined = ValidationResult::combine($r1, $r2);

    expect($combined->isInvalid())->toBeTrue()
        ->and($combined->errors())->toHaveCount(3);
});

it('combines empty results as valid', function () {
    $combined = ValidationResult::combine();

    expect($combined->isValid())->toBeTrue()
        ->and($combined->value())->toBe([]);
});
