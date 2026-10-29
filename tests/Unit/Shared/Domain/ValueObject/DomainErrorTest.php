<?php

declare(strict_types=1);

use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\ErrorKind;

it('creates a domain error with required fields', function () {
    $error = new DomainError(
        code: 'CPF_INVALID',
        message: 'O CPF deve conter 11 dígitos.',
        kind: ErrorKind::Validation,
    );

    expect($error->code)->toBe('CPF_INVALID')
        ->and($error->message)->toBe('O CPF deve conter 11 dígitos.')
        ->and($error->kind)->toBe(ErrorKind::Validation)
        ->and($error->field)->toBeNull();
});

it('creates a domain error with optional field', function () {
    $error = new DomainError(
        code: 'EMAIL_INVALID',
        message: 'O e-mail informado é inválido.',
        kind: ErrorKind::Validation,
        field: 'email',
    );

    expect($error->code)->toBe('EMAIL_INVALID')
        ->and($error->message)->toBe('O e-mail informado é inválido.')
        ->and($error->kind)->toBe(ErrorKind::Validation)
        ->and($error->field)->toBe('email');
});

it('creates a domain error with null field explicitly', function () {
    $error = new DomainError(
        code: 'USER_CPF_ALREADY_EXISTS',
        message: 'Já existe um usuário com este CPF.',
        kind: ErrorKind::Conflict,
        field: null,
    );

    expect($error->kind)->toBe(ErrorKind::Conflict)
        ->and($error->field)->toBeNull();
});
