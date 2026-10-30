<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documenta a resposta `422 Unprocessable Entity`.
 *
 * Os códigos possíveis são derivados das chaves de `$examples`, e os campos de
 * `details[].field` das chaves de `$fields`, de modo que a documentação não
 * divirja dos erros realmente retornados pelo endpoint.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidationErrorResponse extends ApiErrorResponse
{
    /**
     * @param array<string, ApiExample> $examples exemplos nomeados pelo código do erro
     * @param array<string, string>     $fields   campos possíveis em `details[].field` => descrição
     */
    public function __construct(
        array $examples = [],
        array $fields = [],
    ) {
        parent::__construct(
            response: Response::HTTP_UNPROCESSABLE_ENTITY,
            description: 'Erros de validação de entrada.',
            examples: [] === $examples ? self::defaultExamples() : $examples,
            fields: $fields,
        );
    }

    /**
     * @return array<string, ApiExample>
     */
    private static function defaultExamples(): array
    {
        return [
            'VALIDATION_ERROR' => new ApiExample(
                summary: 'Erros de Validação',
                description: 'Um ou mais campos do payload contêm dados inválidos.',
                value: [
                    'errors' => [
                        [
                            'code' => 'VALIDATION_ERROR',
                            'message' => 'Um ou mais erros de validação foram encontrados.',
                            'details' => [
                                [
                                    'field' => 'email',
                                    'message' => 'O email é obrigatório.',
                                ],
                            ],
                        ],
                    ],
                ],
            ),
        ];
    }
}
