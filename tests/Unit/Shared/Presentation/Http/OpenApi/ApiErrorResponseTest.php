<?php

declare(strict_types=1);

use App\Shared\Presentation\Http\OpenApi\ApiErrorExample;
use App\Shared\Presentation\Http\OpenApi\ApiErrorResponse;
use App\Shared\Presentation\Http\OpenApi\ApiMeta;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

it('adds custom meta to the error schema', function () {
    $response = new ApiErrorResponse(
        response: Response::HTTP_CONFLICT,
        description: 'Conflito.',
        examples: [
            'SOCIAL_ACCOUNT_LINK_REQUIRED' => new ApiErrorExample(message: 'Mensagem de erro.'),
        ],
        meta: new ApiMeta(
            required: ['link'],
            properties: [
                new OA\Property(property: 'link', type: 'string', example: 'token'),
            ],
        ),
    );

    $properties = $response->_unmerged[0]->allOf[1]->properties;

    expect($properties)->toHaveCount(2)
        ->and($properties[0]->property)->toBe('errors')
        ->and($properties[1]->property)->toBe('meta')
        ->and($properties[1]->allOf[0]->ref)->toBe('#/components/schemas/ApiResponseMeta')
        ->and($properties[1]->allOf[1]->properties[0]->property)->toBe('link');
});

it('keeps the error schema unchanged without custom meta', function () {
    $response = new ApiErrorResponse(
        response: Response::HTTP_CONFLICT,
        description: 'Conflito.',
        examples: [
            'CODE' => new ApiErrorExample(message: 'Mensagem de erro.'),
        ],
    );

    $properties = $response->_unmerged[0]->allOf[1]->properties;

    expect($properties)->toHaveCount(1)
        ->and($properties[0]->property)->toBe('errors');
});
