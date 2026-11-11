<?php

declare(strict_types=1);

use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;

it('builds one error item with the given code and message', function () {
    $example = new ApiErrorExample(message: 'Mensagem de erro.');

    expect($example->toErrors('RESOURCE_CONFLICT'))->toBe([
        [
            'code' => 'RESOURCE_CONFLICT',
            'message' => 'Mensagem de erro.',
        ],
    ]);
});

it('includes the field when informed', function () {
    $example = new ApiErrorExample(message: 'O e-mail é obrigatório.', field: 'email');

    expect($example->toErrors('FIELD_REQUIRED'))->toBe([
        [
            'code' => 'FIELD_REQUIRED',
            'field' => 'email',
            'message' => 'O e-mail é obrigatório.',
        ],
    ]);
});

it('builds additional errors from the same example', function () {
    $example = new ApiErrorExample(
        message: 'O título é obrigatório.',
        field: 'title',
        additional: [
            ['code' => 'INVALID_FORMAT', 'field' => 'startsAt', 'message' => 'Data inválida.'],
        ],
    );

    expect($example->toErrors('FIELD_REQUIRED'))->toBe([
        [
            'code' => 'FIELD_REQUIRED',
            'field' => 'title',
            'message' => 'O título é obrigatório.',
        ],
        [
            'code' => 'INVALID_FORMAT',
            'field' => 'startsAt',
            'message' => 'Data inválida.',
        ],
    ]);
});

it('exposes every code from the example', function () {
    $example = new ApiErrorExample(
        message: 'O título é obrigatório.',
        field: 'title',
        additional: [
            ['code' => 'INVALID_FORMAT', 'field' => 'startsAt', 'message' => 'Data inválida.'],
            ['code' => 'INVALID_FORMAT', 'field' => 'endsAt', 'message' => 'Data inválida.'],
        ],
    );

    expect($example->codes('FIELD_REQUIRED'))->toBe(['FIELD_REQUIRED', 'INVALID_FORMAT']);
});

it('exposes the detail fields', function () {
    $example = new ApiErrorExample(
        message: 'Mensagem.',
        field: 'title',
        additional: [
            ['code' => 'INVALID_FORMAT', 'field' => 'startsAt', 'message' => 'Data inválida.'],
        ],
    );

    expect($example->detailFields())->toBe(['title', 'startsAt']);
});

it('rejects a detail field that is not declared in fields', function () {
    expect(fn () => new ValidationErrorResponse(
        examples: [
            'INVALID_FORMAT' => new ApiErrorExample(
                message: 'O CPF é inválido.',
                field: 'cpf',
            ),
        ],
        fields: ['email' => 'E-mail do usuário.'],
    ))->toThrow(InvalidArgumentException::class);
});
