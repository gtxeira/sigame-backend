<?php

declare(strict_types=1);

use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\Functional\ValidationResult;

it('creates a success result with value', function () {
    $result = Result::success('hello');

    expect($result->isSuccess())->toBeTrue()
        ->and($result->isFailure())->toBeFalse()
        ->and($result->value())->toBe('hello')
        ->and($result->errors())->toBe([]);
});

it('creates a failure result with errors', function () {
    $errors = [
        new DomainError(code: 'CPF_INVALID', message: 'CPF inválido.', field: 'cpf'),
    ];

    $result = Result::failure($errors);

    expect($result->isSuccess())->toBeFalse()
        ->and($result->isFailure())->toBeTrue()
        ->and($result->errors())->toHaveCount(1)
        ->and($result->errors()[0]->code)->toBe('CPF_INVALID');
});

it('throws logic exception when accessing value on failure', function () {
    $result = Result::failure([
        new DomainError(code: 'ERROR', message: 'Erro.'),
    ]);

    $result->value();
})->throws(LogicException::class);

it('creates result from valid validation result', function () {
    $validation = ValidationResult::valid('hello');

    $result = Result::fromValidation($validation);

    expect($result->isSuccess())->toBeTrue()
        ->and($result->value())->toBe('hello');
});

it('creates result from invalid validation result', function () {
    $validation = ValidationResult::invalid([
        new DomainError(code: 'CPF_INVALID', message: 'CPF inválido.', field: 'cpf'),
    ]);

    $result = Result::fromValidation($validation);

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors())->toHaveCount(1)
        ->and($result->errors()[0]->code)->toBe('CPF_INVALID');
});

it('maps success value', function () {
    $result = Result::success(5);

    $mapped = $result->map(fn (int $v) => $v * 2);

    expect($mapped->isSuccess())->toBeTrue()
        ->and($mapped->value())->toBe(10);
});

it('map is no-op on failure', function () {
    $result = Result::failure([
        new DomainError(code: 'ERROR', message: 'Erro.'),
    ]);

    $mapped = $result->map(fn ($v) => 'nunca');

    expect($mapped->isFailure())->toBeTrue()
        ->and($mapped->errors())->toHaveCount(1);
});

it('chains operations with then', function () {
    $result = Result::success(5);

    $chained = $then = $result->then(function (int $v) {
        return Result::success($v + 10);
    });

    expect($chained->isSuccess())->toBeTrue()
        ->and($chained->value())->toBe(15);
});

it('then is no-op on failure', function () {
    $result = Result::failure([
        new DomainError(code: 'ERROR', message: 'Erro.'),
    ]);

    $chained = $result->then(function ($v) {
        return Result::success('nunca');
    });

    expect($chained->isFailure())->toBeTrue()
        ->and($chained->errors())->toHaveCount(1);
});

it('then propagates failure from chained operation', function () {
    $result = Result::success(5);

    $chained = $result->then(function (int $v) {
        return Result::failure([
            new DomainError(code: 'CHAIN_ERROR', message: 'Falha no encadeamento.'),
        ]);
    });

    expect($chained->isFailure())->toBeTrue()
        ->and($chained->errors()[0]->code)->toBe('CHAIN_ERROR');
});
