<?php

declare(strict_types=1);

use App\Shared\Domain\Error\SharedErrors;

it('builds cpf error for invalid length', function () {
    $error = SharedErrors::cpfInvalidLength();

    expect($error->code)->toBe('CPF_INVALID')
        ->and($error->message)->toBe('O CPF deve conter exatamente 11 dígitos.')
        ->and($error->field)->toBe('cpf');
});

it('builds cpf error for repeated digits', function () {
    $error = SharedErrors::cpfAllDigitsEqual();

    expect($error->code)->toBe('CPF_INVALID')
        ->and($error->message)->toBe('O CPF não pode conter todos os dígitos iguais.')
        ->and($error->field)->toBe('cpf');
});

it('builds cpf error for invalid check digits', function () {
    $error = SharedErrors::cpfInvalidCheckDigits();

    expect($error->code)->toBe('CPF_INVALID')
        ->and($error->message)->toBe('O CPF possui dígitos verificadores inválidos.')
        ->and($error->field)->toBe('cpf');
});

it('builds email error', function () {
    $error = SharedErrors::emailInvalid();

    expect($error->code)->toBe('EMAIL_INVALID')
        ->and($error->message)->toBe('O e-mail informado é inválido.')
        ->and($error->field)->toBe('email');
});

it('builds datetime format error', function () {
    $error = SharedErrors::dateTimeInvalidFormat();

    expect($error->code)->toBe('DATE_TIME_INVALID')
        ->and($error->message)->toBe('Informe uma data RFC 3339 (com ou sem milissegundos) com timezone.')
        ->and($error->field)->toBe('datetime');
});

it('builds invalid timestamp error', function () {
    $error = SharedErrors::dateTimeInvalidTimestamp();

    expect($error->code)->toBe('DATE_TIME_INVALID')
        ->and($error->message)->toBe('O timestamp informado é inválido.')
        ->and($error->field)->toBe('timestamp');
});

it('builds datetime invalid amount error with contextual message', function () {
    $error = SharedErrors::dateTimeInvalidAmount('days', 'adicionar');

    expect($error->code)->toBe('DATE_TIME_INVALID')
        ->and($error->message)->toBe('Informe um valor positivo maior que zero para adicionar days.')
        ->and($error->field)->toBe('days');
});

it('builds datetime invalid shift error with contextual field', function () {
    $error = SharedErrors::dateTimeInvalidShift('hours');

    expect($error->code)->toBe('DATE_TIME_INVALID')
        ->and($error->message)->toBe('Não foi possível modificar o datetime em hours.')
        ->and($error->field)->toBe('hours');
});
