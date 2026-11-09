<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documenta a resposta `404 Not Found`.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class NotFoundResponse extends ApiErrorResponse
{
    /**
     * @param array<string, ApiErrorExample> $examples
     * @param array<string, string>          $fields
     * @param ApiMeta|null                   $meta     campos adicionais de `meta` além do timestamp
     */
    public function __construct(array $examples = [], array $fields = [], ?ApiMeta $meta = null)
    {
        parent::__construct(
            response: Response::HTTP_NOT_FOUND,
            description: 'O recurso solicitado não existe.',
            examples: [] === $examples ? [
                'NOT_FOUND' => new ApiErrorExample(
                    message: 'O recurso solicitado não foi encontrado.',
                    summary: 'Recurso não encontrado',
                    description: 'O identificador informado não corresponde a um recurso existente.',
                ),
            ] : $examples,
            fields: $fields,
            meta: $meta,
        );
    }
}
