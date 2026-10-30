<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\OpenApi;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Undefined;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Preenche o `value` de exemplos nomeados a partir do schema da resposta.
 *
 * O Nelmio executa processors customizados depois de resolver `#[Model]` e de
 * registrar os schemas em `components`, então aqui o documento já está no
 * formato final: basta percorrer `responses.*.content.*.examples.*` e derivar o
 * body do schema do media type. Exemplos com `value` explícito são preservados.
 */
#[Autoconfigure(tags: ['nelmio_api_doc.swagger.processor'])]
final class FillNamedExampleValues
{
    public function __construct(
        private readonly ExampleValueResolver $resolver,
    ) {
    }

    public function __invoke(Analysis $analysis): void
    {
        $openApi = $analysis->openapi;

        if (null === $openApi) {
            return;
        }

        $components = $this->components($openApi);

        foreach ($analysis->getAnnotationsOfType(OA\Response::class) as $response) {
            if (!is_array($response->content)) {
                continue;
            }

            foreach ($response->content as $mediaType) {
                if (!$mediaType instanceof OA\MediaType) {
                    continue;
                }

                $this->fill($mediaType, $components);
            }
        }
    }

    /**
     * @param array<string, OA\Schema> $components
     */
    private function fill(OA\MediaType $mediaType, array $components): void
    {
        if (Undefined::isDefault($mediaType->examples) || Undefined::isDefault($mediaType->schema)) {
            return;
        }

        foreach ($mediaType->examples as $example) {
            if (!Undefined::isDefault($example->value)) {
                continue;
            }

            $value = $this->resolver->resolve($mediaType->schema, $components);

            if (Undefined::isDefault($value)) {
                $example->_context->logger->warning(sprintf(
                    'Não foi possível derivar o value do exemplo "%s" a partir do schema da resposta.',
                    (string) $example->example,
                ));

                continue;
            }

            $example->value = $this->withMetaLast($value);
        }
    }

    /**
     * Mantém `meta` como último campo do body, seguindo a ordem editorial do
     * envelope de resposta (dados primeiro, metadados depois).
     */
    private function withMetaLast(mixed $value): mixed
    {
        if (!is_array($value) || !array_key_exists('meta', $value)) {
            return $value;
        }

        $meta = $value['meta'];
        unset($value['meta']);
        $value['meta'] = $meta;

        return $value;
    }

    /**
     * @return array<string, OA\Schema>
     */
    private function components(OA\OpenApi $openApi): array
    {
        if (Undefined::isDefault($openApi->components) || Undefined::isDefault($openApi->components->schemas)) {
            return [];
        }

        $components = [];

        foreach ($openApi->components->schemas as $schema) {
            if (Undefined::isDefault($schema->schema)) {
                continue;
            }

            $components[$schema->schema] = $schema;
        }

        return $components;
    }
}
