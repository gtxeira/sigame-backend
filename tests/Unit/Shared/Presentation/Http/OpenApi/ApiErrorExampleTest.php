<?php

declare(strict_types=1);

use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\ValidationErrorResponse;

it('builds the error with the given code and message', function () {
    $example = new ApiErrorExample(message: 'Mensagem de erro.');

    expect($example->toError('RESOURCE_CONFLICT'))->toBe([
        'code' => 'RESOURCE_CONFLICT',
        'message' => 'Mensagem de erro.',
    ]);
});

it('builds details from a list of fields', function () {
    $example = new ApiErrorExample(
        message: 'Mensagem de erro.',
        details: ['cpf', 'email'],
    );

    expect($example->toError('CODE')['details'])->toBe([
        ['field' => 'cpf'],
        ['field' => 'email'],
    ]);
});

it('builds details from a map of fields and messages', function () {
    $example = new ApiErrorExample(
        message: 'Mensagem de erro.',
        details: ['email' => 'O email é obrigatório.', 'cpf' => null],
    );

    expect($example->toError('CODE')['details'])->toBe([
        ['field' => 'email', 'message' => 'O email é obrigatório.'],
        ['field' => 'cpf'],
    ]);
});

it('exposes the detail fields', function () {
    $list = new ApiErrorExample(message: 'Mensagem.', details: ['cpf', 'email']);
    $map = new ApiErrorExample(message: 'Mensagem.', details: ['email' => 'Mensagem.', 'cpf' => null]);

    expect($list->detailFields())->toBe(['cpf', 'email'])
        ->and($map->detailFields())->toBe(['email', 'cpf']);
});

it('rejects a detail field that is not declared in fields', function () {
    expect(fn () => new ValidationErrorResponse(
        examples: [
            'CPF_INVALID' => new ApiErrorExample(
                message: 'O CPF é inválido.',
                details: ['cpf'],
            ),
        ],
        fields: ['email' => 'E-mail do usuário.'],
    ))->toThrow(InvalidArgumentException::class);
});
