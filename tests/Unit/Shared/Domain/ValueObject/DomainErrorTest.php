<?php

declare(strict_types=1);

use App\Shared\Domain\Error\DomainError;

it('creates a domain error with required fields', function () {
    $error = new DomainError(
        code: 'CPF_INVALID',
        message: 'O CPF deve conter 11 dígitos.',
    );

    expect($error->code)->toBe('CPF_INVALID')
        ->and($error->message)->toBe('O CPF deve conter 11 dígitos.')
        ->and($error->field)->toBeNull();
});

it('creates a domain error with optional field', function () {
    $error = new DomainError(
        code: 'EMAIL_INVALID',
        message: 'O e-mail informado é inválido.',
        field: 'email',
    );

    expect($error->code)->toBe('EMAIL_INVALID')
        ->and($error->message)->toBe('O e-mail informado é inválido.')
        ->and($error->field)->toBe('email');
});

it('creates a domain error with null field explicitly', function () {
    $error = new DomainError(
        code: 'INTERNAL_ERROR',
        message: 'Ocorreu um erro interno.',
        field: null,
    );

    expect($error->field)->toBeNull();
});
