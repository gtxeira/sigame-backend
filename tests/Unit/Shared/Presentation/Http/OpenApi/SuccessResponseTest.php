<?php

declare(strict_types=1);

use App\Identity\Presentation\Http\Response\RegisterUserResponse;
use App\Shared\Presentation\Http\OpenApi\ApiExample;
use App\Shared\Presentation\Http\OpenApi\ApiMeta;
use App\Shared\Presentation\Http\OpenApi\SuccessResponse;
use OpenApi\Attributes as OA;
use OpenApi\Undefined;
use Symfony\Component\HttpFoundation\Response;

it('attaches named examples with title and description', function () {
    $value = [
        'data' => ['id' => '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f'],
        'meta' => ['timestamp' => '2026-09-21T10:00:00.000+00:00'],
    ];

    $response = new SuccessResponse(
        dataModel: RegisterUserResponse::class,
        response: Response::HTTP_CREATED,
        description: 'Usuário cadastrado com sucesso.',
        examples: [
            'USUARIO_CADASTRADO' => new ApiExample(
                summary: 'Usuário cadastrado',
                description: 'Cadastro concluído com sucesso.',
                value: $value,
            ),
        ],
    );

    $content = successMediaType($response);

    expect($response->response)->toBe(Response::HTTP_CREATED)
        ->and($response->description)->toBe('Usuário cadastrado com sucesso.')
        ->and($content->mediaType)->toBe('application/json')
        ->and($content->examples)->toHaveCount(1)
        ->and($content->examples[0])->toBeInstanceOf(OA\Examples::class)
        ->and($content->examples[0]->example)->toBe('USUARIO_CADASTRADO')
        ->and($content->examples[0]->summary)->toBe('Usuário cadastrado')
        ->and($content->examples[0]->description)->toBe('Cadastro concluído com sucesso.')
        ->and($content->examples[0]->value)->toBe($value);
});

it('keeps the response without examples when none is given', function () {
    $response = new SuccessResponse();

    $content = successMediaType($response);

    expect(Undefined::isDefault($content->examples))->toBeTrue();
});

it('keeps the data schema built from the given properties', function () {
    $response = new SuccessResponse(
        dataRequired: ['status'],
        dataProperties: [
            new OA\Property(property: 'status', type: 'string', const: 'UP'),
        ],
    );

    $content = successMediaType($response);

    $dataProperty = $content->schema->allOf[1]->properties[0];

    expect($dataProperty->property)->toBe('data')
        ->and($dataProperty->required)->toBe(['status'])
        ->and($dataProperty->properties[0]->property)->toBe('status');
});

it('adds custom meta properties to the success schema', function () {
    $response = new SuccessResponse(
        dataProperties: [
            new OA\Property(property: 'status', type: 'string', example: 'UP'),
        ],
        meta: new ApiMeta(
            properties: [
                new OA\Property(property: 'requestId', type: 'string', example: 'req-1'),
            ],
        ),
    );

    $content = successMediaType($response);
    $properties = $content->schema->allOf[1]->properties;

    expect($properties)->toHaveCount(2)
        ->and($properties[0]->property)->toBe('data')
        ->and($properties[1]->property)->toBe('meta')
        ->and($properties[1]->allOf[0]->ref)->toBe('#/components/schemas/ApiResponseMeta')
        ->and($properties[1]->allOf[1]->properties[0]->property)->toBe('requestId');
});

it('rejects combining a data model with inline properties', function () {
    expect(fn () => new SuccessResponse(
        dataModel: RegisterUserResponse::class,
        dataProperties: [
            new OA\Property(property: 'status', type: 'string'),
        ],
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects required fields without inline properties', function () {
    expect(fn () => new SuccessResponse(
        dataRequired: ['status'],
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects a required field that is not declared in inline properties', function () {
    expect(fn () => new SuccessResponse(
        dataRequired: ['status'],
        dataProperties: [
            new OA\Property(property: 'checks', type: 'object'),
        ],
    ))->toThrow(InvalidArgumentException::class);
});
