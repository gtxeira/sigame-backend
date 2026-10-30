<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use OpenApi\Attributes as OA;

/**
 * Documenta uma resposta de erro da API.
 *
 * Os códigos possíveis são derivados das chaves de `$examples`, de modo que
 * o enum do schema e os exemplos nunca divergem. Códigos internos ou
 * inalcançáveis no endpoint não devem ser declarados aqui.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiErrorResponse extends OA\Response
{
    /**
     * @param array<string, ApiErrorExample> $examples exemplos nomeados pelo código do erro
     * @param array<string, string>          $fields   campos possíveis em `details[].field` => descrição
     */
    public function __construct(
        int $response,
        string $description,
        array $examples,
        array $fields = [],
    ) {
        parent::__construct(
            response: $response,
            description: $description,
            content: new OA\JsonContent(
                examples: self::buildExamples($examples),
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/ApiResponseError'),
                    new OA\Schema(
                        properties: [
                            new OA\Property(
                                property: 'errors',
                                type: 'array',
                                items: new OA\Items(
                                    required: ['code', 'message'],
                                    properties: [
                                        new OA\Property(
                                            property: 'code',
                                            description: 'Código público do erro. É o contrato estável: novos códigos podem ser adicionados, os existentes não mudam de significado.',
                                            type: 'string',
                                            enum: array_keys($examples),
                                        ),
                                        new OA\Property(property: 'message', type: 'string'),
                                        new OA\Property(
                                            property: 'details',
                                            description: 'Detalhes do erro. `field` indica o campo afetado; `message` só é preenchido quando `code` é VALIDATION_ERROR.',
                                            type: 'array',
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(
                                                        property: 'field',
                                                        description: self::describeFields($fields),
                                                        type: 'string',
                                                        enum: [] === $fields ? null : array_keys($fields),
                                                    ),
                                                    new OA\Property(
                                                        property: 'message',
                                                        description: 'Mensagem de validação do campo. Presente apenas quando `code` é VALIDATION_ERROR.',
                                                        type: 'string',
                                                    ),
                                                ],
                                                type: 'object',
                                            ),
                                        ),
                                    ],
                                    type: 'object',
                                ),
                            ),
                        ],
                        type: 'object',
                    ),
                ],
            ),
        );
    }

    /**
     * @param array<string, ApiErrorExample> $examples
     *
     * @return list<OA\Examples>
     */
    private static function buildExamples(array $examples): array
    {
        $built = [];

        foreach ($examples as $code => $example) {
            $built[] = new OA\Examples(
                example: $code,
                summary: $example->summary,
                description: $example->when,
                value: $example->value,
            );
        }

        return $built;
    }

    /**
     * @param array<string, string> $fields
     */
    private static function describeFields(array $fields): string
    {
        if ([] === $fields) {
            return 'Campo afetado pelo erro, quando aplicável.';
        }

        $lines = ['Campos que podem aparecer em `details[].field`:'];

        foreach ($fields as $field => $description) {
            $lines[] = sprintf('- `%s`: %s', $field, $description);
        }

        return implode("\n", $lines);
    }
}
