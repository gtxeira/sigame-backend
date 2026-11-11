<?php

declare(strict_types=1);

use App\Identity\Domain\User\Exception\CpfAlreadyExistsException;
use App\Shared\Domain\Error\DomainError;
use App\Shared\Domain\Error\ErrorKind;
use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Presentation\Http\Response\ApiResponseErrorsMapper;
use App\Shared\Presentation\Http\Validation\ConstraintViolationCodeResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

it('flattens domain errors into one item per error', function () {
    $errors = [
        new DomainError('EMAIL_ALREADY_EXISTS', 'Já existe um usuário com este e-mail.', ErrorKind::Conflict, 'email'),
        new DomainError('USER_CPF_ALREADY_EXISTS', 'Já existe um usuário com este CPF.', ErrorKind::Conflict, 'cpf'),
    ];

    expect(ApiResponseErrorsMapper::fromDomainErrors($errors))->toEqual([
        [
            'code' => 'EMAIL_ALREADY_EXISTS',
            'message' => 'Já existe um usuário com este e-mail.',
            'field' => 'email',
        ],
        [
            'code' => 'USER_CPF_ALREADY_EXISTS',
            'field' => 'cpf',
            'message' => 'Já existe um usuário com este CPF.',
        ],
    ]);
});

it('omits the field when the domain error has no field', function () {
    $errors = [new DomainError('TOO_MANY_REQUESTS', 'Muitas tentativas.', ErrorKind::Validation)];

    expect(ApiResponseErrorsMapper::fromDomainErrors($errors))->toEqual([
        [
            'code' => 'TOO_MANY_REQUESTS',
            'message' => 'Muitas tentativas.',
        ],
    ]);
});

it('flattens a domain exception into a single item', function () {
    $items = ApiResponseErrorsMapper::fromDomainException(CpfAlreadyExistsException::create());

    expect($items)->toEqual([
        [
            'code' => 'USER_CPF_ALREADY_EXISTS',
            'field' => 'cpf',
            'message' => 'Já existe um usuário com este CPF.',
        ],
    ]);
});

it('keeps one item per violation and drops other errors when the field is required', function () {
    $violations = new ConstraintViolationList([
        new ConstraintViolation(
            message: 'O nome é obrigatório.',
            messageTemplate: 'O nome é obrigatório.',
            parameters: [],
            root: null,
            propertyPath: 'name',
            invalidValue: '',
            code: null,
            constraint: new NotBlank(),
        ),
        new ConstraintViolation(
            message: 'O nome deve ter no mínimo 3 caracteres.',
            messageTemplate: 'O nome deve ter no mínimo 3 caracteres.',
            parameters: [],
            root: null,
            propertyPath: 'name',
            invalidValue: '',
            code: Length::TOO_SHORT_ERROR,
            constraint: new Length(min: 3),
        ),
    ]);

    $items = ApiResponseErrorsMapper::fromViolations($violations, new ConstraintViolationCodeResolver());

    expect($items)->toEqual([
        [
            'code' => 'FIELD_REQUIRED',
            'message' => 'O nome é obrigatório.',
            'field' => 'name',
        ],
    ]);
});

it('keeps independent violations from different fields', function () {
    $violations = new ConstraintViolationList([
        new ConstraintViolation(
            message: 'O nome é obrigatório.',
            messageTemplate: 'O nome é obrigatório.',
            parameters: [],
            root: null,
            propertyPath: 'name',
            invalidValue: '',
            code: null,
            constraint: new NotBlank(),
        ),
        new ConstraintViolation(
            message: 'A senha deve ter no mínimo 8 caracteres.',
            messageTemplate: 'A senha deve ter no mínimo 8 caracteres.',
            parameters: [],
            root: null,
            propertyPath: 'password',
            invalidValue: 'abc',
            code: Length::TOO_SHORT_ERROR,
            constraint: new Length(min: 8),
        ),
    ]);

    $items = ApiResponseErrorsMapper::fromViolations($violations, new ConstraintViolationCodeResolver());

    expect($items)->toEqual([
        [
            'code' => 'FIELD_REQUIRED',
            'message' => 'O nome é obrigatório.',
            'field' => 'name',
        ],
        [
            'code' => 'STRING_TOO_SHORT',
            'message' => 'A senha deve ter no mínimo 8 caracteres.',
            'field' => 'password',
        ],
    ]);
});

it('builds a validation error through the factory used by request helpers', function () {
    $error = ValidationErrors::invalidFormat('cpf', 'O CPF é inválido.');

    expect(ApiResponseErrorsMapper::fromDomainErrors([$error]))->toBe([
        [
            'code' => 'INVALID_FORMAT',
            'message' => 'O CPF é inválido.',
            'field' => 'cpf',
        ],
    ]);
});
