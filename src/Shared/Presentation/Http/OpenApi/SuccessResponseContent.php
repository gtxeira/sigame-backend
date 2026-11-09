<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Monta o conteúdo JSON comum às respostas de sucesso.
 *
 * Centraliza o envelope `data` + `meta` e os exemplos nomeados para que os
 * atributos de sucesso não dupliquem a estrutura do schema.
 */
final class SuccessResponseContent
{
    /**
     * @param array<string, ApiExample> $examples exemplos nomeados exibidos na documentação
     */
    public static function build(
        OA\Property $dataProperty,
        ?OA\Property $metaProperty,
        array $examples,
    ): OA\MediaType {
        $properties = [$dataProperty];

        if (null !== $metaProperty) {
            $properties[] = $metaProperty;
        }

        $schema = new OA\Schema(allOf: [
            new OA\Schema(
                ref: '#/components/schemas/ApiResponseSuccess',
            ),
            new OA\Schema(
                properties: $properties,
            ),
        ]);

        return [] === $examples
            ? new OA\MediaType(
                mediaType: 'application/json',
                schema: $schema,
            )
            : new OA\MediaType(
                mediaType: 'application/json',
                schema: $schema,
                examples: ApiExamples::build($examples),
            );
    }
}
