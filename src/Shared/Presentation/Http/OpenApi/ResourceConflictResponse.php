<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documenta a resposta `409 Conflict`.
 *
 * Os códigos possíveis são derivados das chaves de `$examples`, e os campos de
 * `details[].field` das chaves de `$fields`, de modo que a documentação não
 * divirja dos erros realmente retornados pelo endpoint.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ResourceConflictResponse extends ApiErrorResponse
{
    /**
     * @param array<string, ApiErrorExample> $examples exemplos nomeados pelo código do erro
     * @param array<string, string>          $fields   campos possíveis em `details[].field` => descrição
     * @param ApiMeta|null                   $meta     campos adicionais de `meta` além do timestamp
     */
    public function __construct(
        array $examples = [],
        array $fields = [],
        ?ApiMeta $meta = null,
    ) {
        parent::__construct(
            response: Response::HTTP_CONFLICT,
            description: 'A operação conflita com o estado atual do recurso.',
            examples: [] === $examples ? self::defaultExamples() : $examples,
            fields: $fields,
            meta: $meta,
        );
    }

    /**
     * @return array<string, ApiErrorExample>
     */
    private static function defaultExamples(): array
    {
        return [
            'RESOURCE_CONFLICT' => new ApiErrorExample(
                message: 'A operação conflita com o estado atual do recurso.',
                summary: 'Conflito de regra de negócio ou integridade',
                description: 'A requisição entra em conflito com o estado do recurso.',
            ),
        ];
    }
}
