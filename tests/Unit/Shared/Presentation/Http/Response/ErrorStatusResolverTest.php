<?php

declare(strict_types=1);

use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\ErrorKind;
use App\Shared\Presentation\Http\Response\ErrorStatusResolver;
use Symfony\Component\HttpFoundation\Response;

it('maps validation kind to 422', function () {
    expect((new ErrorStatusResolver())->fromKind(ErrorKind::Validation))
        ->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
});

it('maps conflict kind to 409', function () {
    expect((new ErrorStatusResolver())->fromKind(ErrorKind::Conflict))
        ->toBe(Response::HTTP_CONFLICT);
});

it('resolves an empty error list as validation', function () {
    expect((new ErrorStatusResolver())->fromErrors([]))
        ->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
});

it('resolves a single validation error to 422', function () {
    $errors = [
        new DomainError(code: 'CPF_INVALID', message: 'CPF inválido.', kind: ErrorKind::Validation),
    ];

    expect((new ErrorStatusResolver())->fromErrors($errors))
        ->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
});

it('resolves a single conflict error to 409', function () {
    $errors = [
        new DomainError(code: 'USER_CPF_ALREADY_EXISTS', message: 'CPF já cadastrado.', kind: ErrorKind::Conflict),
    ];

    expect((new ErrorStatusResolver())->fromErrors($errors))
        ->toBe(Response::HTTP_CONFLICT);
});

it('prefers validation when errors are mixed', function () {
    $errors = [
        new DomainError(code: 'USER_CPF_ALREADY_EXISTS', message: 'CPF já cadastrado.', kind: ErrorKind::Conflict),
        new DomainError(code: 'EMAIL_INVALID', message: 'E-mail inválido.', kind: ErrorKind::Validation),
    ];

    expect((new ErrorStatusResolver())->fromErrors($errors))
        ->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
});
