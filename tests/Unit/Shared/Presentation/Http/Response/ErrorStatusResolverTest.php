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

it('maps authentication kind to 401', function () {
    expect((new ErrorStatusResolver())->fromKind(ErrorKind::Authentication))
        ->toBe(Response::HTTP_UNAUTHORIZED);
});

it('maps forbidden kind to 403', function () {
    expect((new ErrorStatusResolver())->fromKind(ErrorKind::Forbidden))
        ->toBe(Response::HTTP_FORBIDDEN);
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
        new DomainError(code: 'RESOURCE_CONFLICT', message: 'Recurso já cadastrado.', kind: ErrorKind::Conflict),
    ];

    expect((new ErrorStatusResolver())->fromErrors($errors))
        ->toBe(Response::HTTP_CONFLICT);
});

it('prefers validation when errors are mixed', function () {
    $errors = [
        new DomainError(code: 'RESOURCE_CONFLICT', message: 'Recurso já cadastrado.', kind: ErrorKind::Conflict),
        new DomainError(code: 'EMAIL_INVALID', message: 'E-mail inválido.', kind: ErrorKind::Validation),
    ];

    expect((new ErrorStatusResolver())->fromErrors($errors))
        ->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
});

it('prefers authentication over the other kinds', function () {
    $errors = [
        new DomainError(code: 'EMAIL_INVALID', message: 'E-mail inválido.', kind: ErrorKind::Validation),
        new DomainError(code: 'ACCOUNT_SUSPENDED', message: 'Conta suspensa.', kind: ErrorKind::Forbidden),
        new DomainError(code: 'INVALID_CREDENTIALS', message: 'Credenciais inválidas.', kind: ErrorKind::Authentication),
    ];

    expect((new ErrorStatusResolver())->fromErrors($errors))
        ->toBe(Response::HTTP_UNAUTHORIZED);
});

it('prefers forbidden over validation and conflict', function () {
    $errors = [
        new DomainError(code: 'RESOURCE_CONFLICT', message: 'Recurso já cadastrado.', kind: ErrorKind::Conflict),
        new DomainError(code: 'EMAIL_INVALID', message: 'E-mail inválido.', kind: ErrorKind::Validation),
        new DomainError(code: 'ACCOUNT_SUSPENDED', message: 'Conta suspensa.', kind: ErrorKind::Forbidden),
    ];

    expect((new ErrorStatusResolver())->fromErrors($errors))
        ->toBe(Response::HTTP_FORBIDDEN);
});
