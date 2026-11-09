<?php

declare(strict_types=1);

use App\Groups\Presentation\Http\Response\GroupSummaryResponse;
use App\Shared\Presentation\Http\OpenApi\ListSuccessResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

it('documents data as an array of the given item model', function () {
    $response = new ListSuccessResponse(
        itemModel: GroupSummaryResponse::class,
        description: 'Grupos do usuário autenticado.',
    );

    $content = successMediaType($response);
    $dataProperty = $content->schema->allOf[1]->properties[0];
    $items = $dataProperty->items;
    assert($items instanceof OA\Items);

    $ref = $items->ref;
    assert($ref instanceof Model);

    expect($dataProperty->property)->toBe('data')
        ->and($dataProperty->type)->toBe('array')
        ->and($ref->type)->toBe(GroupSummaryResponse::class);
});
