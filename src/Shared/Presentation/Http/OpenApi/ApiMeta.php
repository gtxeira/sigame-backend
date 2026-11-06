<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use InvalidArgumentException;
use OpenApi\Attributes as OA;

/**
 * Campos adicionais documentados em `meta` além do `timestamp` padrão.
 *
 * O `timestamp` é sempre incluído pelo envelope da API; aqui ficam apenas os
 * campos específicos do endpoint, como o ticket de vínculo social.
 */
final readonly class ApiMeta
{
    /**
     * @param list<OA\Property> $properties campos extras de `meta`
     * @param list<string>      $required   nomes dos campos obrigatórios
     */
    public function __construct(
        public array $properties = [],
        public array $required = [],
        public ?string $description = null,
    ) {
        $names = [];

        foreach ($properties as $property) {
            $names[] = $property->property;
        }

        foreach ($required as $name) {
            if (!in_array($name, $names, true)) {
                throw new InvalidArgumentException(sprintf(
                    'O campo "%s" de $required não está declarado em $properties.',
                    $name,
                ));
            }
        }
    }

    public function isEmpty(): bool
    {
        return [] === $this->properties;
    }

    public function toProperty(): OA\Property
    {
        $extra = new OA\Schema(
            properties: $this->properties,
            type: 'object',
        );

        if ([] !== $this->required) {
            $extra->required = $this->required;
        }

        return new OA\Property(
            property: 'meta',
            description: $this->description ?? 'Metadados da resposta. Contém `timestamp` e os campos adicionais documentados para este endpoint.',
            allOf: [
                new OA\Schema(ref: '#/components/schemas/ApiResponseMeta'),
                $extra,
            ],
        );
    }
}
