<?php

declare(strict_types=1);

use App\Identity\Domain\Error\IdentityErrors;

it('builds cpf already exists error', function () {
    $error = IdentityErrors::cpfAlreadyExists();

    expect($error->code)->toBe('USER_CPF_ALREADY_EXISTS')
        ->and($error->message)->toBe('Já existe um usuário com este CPF.')
        ->and($error->field)->toBe('cpf');
});

it('builds email already exists error', function () {
    $error = IdentityErrors::emailAlreadyExists();

    expect($error->code)->toBe('EMAIL_ALREADY_EXISTS')
        ->and($error->message)->toBe('Já existe um usuário com este e-mail.')
        ->and($error->field)->toBe('email');
});

it('builds user level below minimum error', function () {
    $error = IdentityErrors::userLevelBelowMinimum(1);

    expect($error->code)->toBe('USER_LEVEL_INVALID')
        ->and($error->message)->toBe('O nível do usuário deve ser maior ou igual a 1')
        ->and($error->field)->toBe('level');
});

it('builds user level above maximum error', function () {
    $error = IdentityErrors::userLevelAboveMaximum(10);

    expect($error->code)->toBe('USER_LEVEL_INVALID')
        ->and($error->message)->toBe('O nível do usuário deve ser menor ou igual a 10')
        ->and($error->field)->toBe('level');
});
