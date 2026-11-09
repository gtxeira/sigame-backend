<?php

declare(strict_types=1);

use App\Groups\Presentation\Http\Response\GroupSummaryResponse;
use App\Shared\Presentation\Http\OpenApi\PaginatedSuccessResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

it('documents a typed data array with pagination meta', function () {
    $response = new PaginatedSuccessResponse(
        itemModel: GroupSummaryResponse::class,
        description: 'Lista paginada de grupos.',
    );

    $content = successMediaType($response);
    $properties = $content->schema->allOf[1]->properties;
    $dataProperty = $properties[0];
    $metaProperty = $properties[1];

    $items = $dataProperty->items;
    assert($items instanceof OA\Items);

    $ref = $items->ref;
    assert($ref instanceof Model);

    expect($properties)->toHaveCount(2)
        ->and($dataProperty->property)->toBe('data')
        ->and($dataProperty->type)->toBe('array')
        ->and($ref->type)->toBe(GroupSummaryResponse::class)
        ->and($metaProperty->property)->toBe('meta')
        ->and($metaProperty->ref)->toBe('#/components/schemas/ApiResponsePaginatedMeta');
});
