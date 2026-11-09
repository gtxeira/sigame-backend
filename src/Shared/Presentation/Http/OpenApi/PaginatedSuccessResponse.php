<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documenta uma resposta de sucesso paginada.
 *
 * O `data` é uma lista de itens e `meta.pagination` é derivado do schema
 * `ApiResponsePaginatedMeta`. Use somente em endpoints que devolvem essa
 * estrutura via `ApiResponse::withPagination()`.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class PaginatedSuccessResponse extends OA\Response
{
    /**
     * @param class-string              $itemModel modelo de cada item da lista
     * @param array<string, ApiExample> $examples  exemplos nomeados exibidos na documentação
     */
    public function __construct(
        string $itemModel,
        int $response = Response::HTTP_OK,
        string $description = 'Lista paginada de itens.',
        array $examples = [],
    ) {
        parent::__construct(
            response: $response,
            description: $description,
            content: SuccessResponseContent::build(
                dataProperty: new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: $itemModel)),
                ),
                metaProperty: new OA\Property(
                    property: 'meta',
                    ref: '#/components/schemas/ApiResponsePaginatedMeta',
                ),
                examples: $examples,
            ),
        );
    }
}
