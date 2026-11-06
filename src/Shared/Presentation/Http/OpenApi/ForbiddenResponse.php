<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documenta a resposta `403 Forbidden`.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ForbiddenResponse extends ApiErrorResponse
{
    /**
     * @param array<string, ApiErrorExample> $examples
     * @param array<string, string>          $fields
     * @param ApiMeta|null                   $meta     campos adicionais de `meta` além do timestamp
     */
    public function __construct(array $examples = [], array $fields = [], ?ApiMeta $meta = null)
    {
        parent::__construct(
            response: Response::HTTP_FORBIDDEN,
            description: 'A operação não pode ser executada.',
            examples: [] === $examples ? [
                'FORBIDDEN' => new ApiErrorExample(
                    message: 'Acesso negado.',
                    summary: 'Acesso negado',
                    description: 'A conta está suspensa ou não possui permissão.',
                ),
            ] : $examples,
            fields: $fields,
            meta: $meta,
        );
    }
}
