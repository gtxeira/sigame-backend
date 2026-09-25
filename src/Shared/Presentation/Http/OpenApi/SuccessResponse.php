<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class SuccessResponse extends OA\Response
{
    /**
     * @param class-string|null $dataModel
     * @param list<string>      $dataRequired
     * @param list<OA\Property> $dataProperties
     */
    public function __construct(
        ?string $dataModel = null,
        int $response = Response::HTTP_OK,
        string $description = 'Operação realizada com sucesso.',
        array $dataRequired = [],
        array $dataProperties = [],
    ) {
        $dataProperty = match (true) {
            null !== $dataModel => new OA\Property(
                property: 'data',
                ref: new Model(type: $dataModel),
            ),

            [] !== $dataProperties => new OA\Property(
                property: 'data',
                required: $dataRequired,
                properties: $dataProperties,
                type: 'object',
            ),

            default => new OA\Property(
                property: 'data',
                nullable: true,
            ),
        };

        parent::__construct(
            response: $response,
            description: $description,
            content: new OA\JsonContent(
                allOf: [
                    new OA\Schema(
                        ref: '#/components/schemas/ApiResponseSuccess',
                    ),
                    new OA\Schema(
                        properties: [$dataProperty],
                    ),
                ],
            ),
        );
    }
}
