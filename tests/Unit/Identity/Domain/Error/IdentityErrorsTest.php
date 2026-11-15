<?php

declare(strict_types=1);

use App\Identity\Domain\Error\IdentityErrors;
use App\Shared\Domain\Error\ErrorKind;

it('builds cpf already exists error', function () {
    $error = IdentityErrors::cpfAlreadyExists();

    expect($error->code)->toBe('USER_CPF_ALREADY_EXISTS')
        ->and($error->message)->toBe('Já existe um usuário com este CPF.')
        ->and($error->kind)->toBe(ErrorKind::Conflict)
        ->and($error->field)->toBe('cpf');
});

it('builds email already exists error', function () {
    $error = IdentityErrors::emailAlreadyExists();

    expect($error->code)->toBe('EMAIL_ALREADY_EXISTS')
        ->and($error->message)->toBe('Já existe um usuário com este e-mail.')
        ->and($error->kind)->toBe(ErrorKind::Conflict)
        ->and($error->field)->toBe('email');
});

it('builds email already verified error', function () {
    $error = IdentityErrors::emailAlreadyVerified();

    expect($error->code)->toBe('EMAIL_ALREADY_VERIFIED')
        ->and($error->message)->toBe('O e-mail já está verificado.')
        ->and($error->kind)->toBe(ErrorKind::Conflict)
        ->and($error->field)->toBeNull();
});

it('builds otp verification and reset errors', function () {
    $invalid = IdentityErrors::emailVerificationCodeInvalid();
    $expired = IdentityErrors::passwordResetCodeExpired();
    $attempts = IdentityErrors::passwordResetCodeAttemptsExceeded();
    $ticket = IdentityErrors::passwordResetTicketExpired();

    expect($invalid->code)->toBe('EMAIL_VERIFICATION_CODE_INVALID')
        ->and($invalid->kind)->toBe(ErrorKind::Validation)
        ->and($invalid->field)->toBe('code')
        ->and($expired->code)->toBe('PASSWORD_RESET_CODE_EXPIRED')
        ->and($expired->kind)->toBe(ErrorKind::Validation)
        ->and($attempts->code)->toBe('PASSWORD_RESET_CODE_ATTEMPTS_EXCEEDED')
        ->and($attempts->field)->toBe('code')
        ->and($ticket->code)->toBe('PASSWORD_RESET_TICKET_EXPIRED')
        ->and($ticket->field)->toBe('resetToken');
});

it('builds user level below minimum error', function () {
    $error = IdentityErrors::userLevelBelowMinimum(1);

    expect($error->code)->toBe('USER_LEVEL_INVALID')
        ->and($error->message)->toBe('O nível do usuário deve ser maior ou igual a 1')
        ->and($error->kind)->toBe(ErrorKind::Validation)
        ->and($error->field)->toBe('level');
});

it('builds user level above maximum error', function () {
    $error = IdentityErrors::userLevelAboveMaximum(10);

    expect($error->code)->toBe('USER_LEVEL_INVALID')
        ->and($error->message)->toBe('O nível do usuário deve ser menor ou igual a 10')
        ->and($error->kind)->toBe(ErrorKind::Validation)
        ->and($error->field)->toBe('level');
});
