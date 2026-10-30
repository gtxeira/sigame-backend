<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Converte exemplos nomeados em exemplos do OpenAPI.
 *
 * Centraliza a conversão para que respostas de erro e de sucesso produzam
 * exatamente a mesma estrutura de documentação.
 */
final class ApiExamples
{
    /**
     * @param array<string, ApiExample> $examples exemplos nomeados pela chave exibida
     *
     * @return list<OA\Examples>
     */
    public static function build(array $examples): array
    {
        return array_map(
            static fn (string $name, ApiExample $example): OA\Examples => new OA\Examples(
                example: $name,
                summary: $example->summary,
                description: $example->description,
                value: $example->value,
            ),
            array_keys($examples),
            array_values($examples),
        );
    }
}
