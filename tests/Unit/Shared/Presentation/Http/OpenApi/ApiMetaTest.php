<?php

declare(strict_types=1);

use App\Shared\Presentation\Http\OpenApi\ApiMeta;
use OpenApi\Attributes as OA;

it('builds a meta property composed with the standard meta', function () {
    $meta = new ApiMeta(
        required: ['link'],
        properties: [
            new OA\Property(
                property: 'link',
                type: 'object',
                properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'a1b2c3...'),
                ],
            ),
        ],
    );

    $property = $meta->toProperty();

    expect($property->property)->toBe('meta')
        ->and($property->allOf)->toHaveCount(2)
        ->and($property->allOf[0]->ref)->toBe('#/components/schemas/ApiResponseMeta')
        ->and($property->allOf[1]->properties[0]->property)->toBe('link')
        ->and($property->allOf[1]->required)->toBe(['link']);
});

it('is empty when there are no custom properties', function () {
    $meta = new ApiMeta(properties: [
        new OA\Property(property: 'link', type: 'string'),
    ]);

    expect((new ApiMeta())->isEmpty())->toBeTrue()
        ->and($meta->isEmpty())->toBeFalse();
});

it('rejects a required field that is not declared in properties', function () {
    expect(fn () => new ApiMeta(
        required: ['link'],
        properties: [
            new OA\Property(property: 'ticket', type: 'string'),
        ],
    ))->toThrow(InvalidArgumentException::class);
});
