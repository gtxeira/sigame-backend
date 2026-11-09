<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documenta uma resposta de sucesso cujo `data` é uma lista de itens.
 *
 * Use `PaginatedSuccessResponse` quando o endpoint também devolve
 * `meta.pagination` via `ApiResponse::withPagination()`.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ListSuccessResponse extends OA\Response
{
    /**
     * @param class-string              $itemModel modelo de cada item da lista
     * @param array<string, ApiExample> $examples  exemplos nomeados exibidos na documentação
     * @param ApiMeta|null              $meta      campos adicionais de `meta` além do timestamp
     */
    public function __construct(
        string $itemModel,
        int $response = Response::HTTP_OK,
        string $description = 'Lista de itens.',
        array $examples = [],
        ?ApiMeta $meta = null,
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
                metaProperty: null !== $meta && !$meta->isEmpty() ? $meta->toProperty() : null,
                examples: $examples,
            ),
        );
    }
}
