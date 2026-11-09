<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\OpenApi;

use Attribute;
use InvalidArgumentException;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class SuccessResponse extends OA\Response
{
    /**
     * @param class-string|null         $dataModel
     * @param list<string>              $dataRequired
     * @param list<OA\Property>         $dataProperties
     * @param array<string, ApiExample> $examples       exemplos nomeados exibidos na documentação
     * @param ApiMeta|null              $meta           campos adicionais de `meta` além do timestamp
     */
    public function __construct(
        ?string $dataModel = null,
        int $response = Response::HTTP_OK,
        string $description = 'Operação realizada com sucesso.',
        array $dataRequired = [],
        array $dataProperties = [],
        array $examples = [],
        ?ApiMeta $meta = null,
    ) {
        self::assertDataShape($dataModel, $dataRequired, $dataProperties);

        $dataProperty = match (true) {
            null !== $dataModel => new OA\Property(
                property: 'data',
                ref: new Model(type: $dataModel),
            ),

            [] !== $dataProperties => new OA\Property(
                property: 'data',
                required: $dataRequired,
                properties: $dataProperties,
                type: 'object',
            ),

            default => new OA\Property(
                property: 'data',
                nullable: true,
            ),
        };

        parent::__construct(
            response: $response,
            description: $description,
            content: SuccessResponseContent::build(
                dataProperty: $dataProperty,
                metaProperty: null !== $meta && !$meta->isEmpty() ? $meta->toProperty() : null,
                examples: $examples,
            ),
        );
    }

    /**
     * @param list<string>      $dataRequired
     * @param list<OA\Property> $dataProperties
     */
    private static function assertDataShape(?string $dataModel, array $dataRequired, array $dataProperties): void
    {
        if (null !== $dataModel && [] !== $dataProperties) {
            throw new InvalidArgumentException('Use apenas um entre $dataModel e $dataProperties.');
        }

        if ([] !== $dataRequired && [] === $dataProperties) {
            throw new InvalidArgumentException('$dataRequired só pode ser usado com $dataProperties.');
        }

        if ([] === $dataRequired) {
            return;
        }

        $declared = [];

        foreach ($dataProperties as $property) {
            $declared[] = $property->property;
        }

        foreach ($dataRequired as $name) {
            if (!in_array($name, $declared, true)) {
                throw new InvalidArgumentException(sprintf(
                    'O campo "%s" de $dataRequired não está declarado em $dataProperties.',
                    $name,
                ));
            }
        }
    }
}
