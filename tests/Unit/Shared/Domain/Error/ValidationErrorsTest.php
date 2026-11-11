<?php

declare(strict_types=1);

use App\Shared\Domain\Error\ErrorKind;
use App\Shared\Domain\Error\ValidationErrorCode;
use App\Shared\Domain\Error\ValidationErrors;

it('builds a field required error', function () {
    $error = ValidationErrors::fieldRequired('email', 'O e-mail é obrigatório.');

    expect($error->code)->toBe(ValidationErrorCode::FieldRequired->value)
        ->and($error->message)->toBe('O e-mail é obrigatório.')
        ->and($error->kind)->toBe(ErrorKind::Validation)
        ->and($error->field)->toBe('email');
});

it('builds a string too short error', function () {
    $error = ValidationErrors::stringTooShort('name', 'O nome deve ter no mínimo 2 caracteres.');

    expect($error->code)->toBe(ValidationErrorCode::StringTooShort->value)
        ->and($error->message)->toBe('O nome deve ter no mínimo 2 caracteres.')
        ->and($error->kind)->toBe(ErrorKind::Validation)
        ->and($error->field)->toBe('name');
});

it('builds a string too long error', function () {
    $error = ValidationErrors::stringTooLong('email', 'O e-mail deve ter no máximo 255 caracteres.');

    expect($error->code)->toBe(ValidationErrorCode::StringTooLong->value)
        ->and($error->message)->toBe('O e-mail deve ter no máximo 255 caracteres.')
        ->and($error->kind)->toBe(ErrorKind::Validation)
        ->and($error->field)->toBe('email');
});

it('builds a number out of range error', function () {
    $error = ValidationErrors::numberOutOfRange('heightCm', 'A altura deve estar entre 50 e 250 centímetros.');

    expect($error->code)->toBe(ValidationErrorCode::NumberOutOfRange->value)
        ->and($error->message)->toBe('A altura deve estar entre 50 e 250 centímetros.')
        ->and($error->kind)->toBe(ErrorKind::Validation)
        ->and($error->field)->toBe('heightCm');
});

it('builds an invalid format error', function () {
    $error = ValidationErrors::invalidFormat('cpf', 'O CPF possui dígitos verificadores inválidos.');

    expect($error->code)->toBe(ValidationErrorCode::InvalidFormat->value)
        ->and($error->message)->toBe('O CPF possui dígitos verificadores inválidos.')
        ->and($error->kind)->toBe(ErrorKind::Validation)
        ->and($error->field)->toBe('cpf');
});
