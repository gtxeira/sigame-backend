<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\OpenApi;

use BackedEnum;
use OpenApi\Annotations as OA;
use OpenApi\Undefined;
use stdClass;
use UnitEnum;

/**
 * Deriva um valor de exemplo a partir de um schema OpenAPI já resolvido.
 *
 * O OpenAPI não permite referenciar o exemplo gerado a partir de um schema em
 * um Example Object nomeado: o `value` precisa ser literal. Este resolver faz
 * essa derivação para que o body dos exemplos acompanhe as anotações dos DTOs
 * sem precisar ser repetido manualmente.
 */
final class ExampleValueResolver
{
    private const int MAX_DEPTH = 32;

    /**
     * @param array<string, OA\Schema> $components schemas de `components.schemas` por nome
     */
    public function resolve(OA\Schema $schema, array $components): mixed
    {
        return $this->resolveSchema($schema, $components, [], 0);
    }

    /**
     * @param array<string, OA\Schema> $components
     * @param array<string, true>      $path
     */
    private function resolveSchema(OA\Schema $schema, array $components, array $path, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return Undefined::UNDEFINED;
        }

        if (!Undefined::isDefault($schema->example)) {
            return $this->normalize($schema->example);
        }

        if (!Undefined::isDefault($schema->const)) {
            return $this->normalize($schema->const);
        }

        if (!Undefined::isDefault($schema->enum) && is_array($schema->enum) && [] !== $schema->enum) {
            return $this->normalize($schema->enum[0]);
        }

        if (!Undefined::isDefault($schema->default)) {
            return $this->normalize($schema->default);
        }

        if (!Undefined::isDefault($schema->ref) && is_string($schema->ref)) {
            $name = $this->componentName($schema->ref);

            if (null === $name || !isset($components[$name]) || isset($path[$name])) {
                return Undefined::UNDEFINED;
            }

            return $this->resolveSchema(
                $components[$name],
                $components,
                $path + [$name => true],
                $depth + 1,
            );
        }

        $value = Undefined::UNDEFINED;

        if (!Undefined::isDefault($schema->allOf)) {
            foreach ($schema->allOf as $branch) {
                $value = $this->merge(
                    $value,
                    $this->resolveSchema($branch, $components, $path, $depth + 1),
                );
            }
        }

        $value = $this->merge(
            $value,
            $this->resolveProperties($schema, $components, $path, $depth),
        );

        if (!Undefined::isDefault($value)) {
            return $value;
        }

        foreach ([$schema->oneOf, $schema->anyOf] as $alternatives) {
            if (Undefined::isDefault($alternatives) || [] === $alternatives) {
                continue;
            }

            $value = $this->resolveSchema($alternatives[0], $components, $path, $depth + 1);

            if (!Undefined::isDefault($value)) {
                return $value;
            }
        }

        if (!Undefined::isDefault($schema->items)) {
            $item = $this->resolveSchema($schema->items, $components, $path, $depth + 1);

            return Undefined::isDefault($item) ? [] : [$item];
        }

        if (true === $schema->nullable) {
            return null;
        }

        $types = $this->typesOf($schema);

        if (in_array('array', $types, true)) {
            return [];
        }

        if (in_array('object', $types, true)) {
            return new stdClass();
        }

        return Undefined::UNDEFINED;
    }

    /**
     * @param array<string, OA\Schema> $components
     * @param array<string, true>      $path
     */
    private function resolveProperties(OA\Schema $schema, array $components, array $path, int $depth): mixed
    {
        if (Undefined::isDefault($schema->properties) || [] === $schema->properties) {
            return Undefined::UNDEFINED;
        }

        $object = [];

        foreach ($schema->properties as $property) {
            if (Undefined::isDefault($property->property)) {
                continue;
            }

            if (true === $property->writeOnly) {
                continue;
            }

            $value = $this->resolveSchema($property, $components, $path, $depth + 1);

            if (Undefined::isDefault($value)) {
                continue;
            }

            $object[$property->property] = $value;
        }

        return [] === $object ? Undefined::UNDEFINED : $object;
    }

    /**
     * @return list<string>
     */
    private function typesOf(OA\Schema $schema): array
    {
        if (Undefined::isDefault($schema->type)) {
            return [];
        }

        $types = is_array($schema->type) ? $schema->type : [$schema->type];

        return array_values($types);
    }

    private function componentName(string $ref): ?string
    {
        $prefix = '#/components/schemas/';

        if (!str_starts_with($ref, $prefix)) {
            return null;
        }

        $name = substr($ref, strlen($prefix));

        if ('' === $name || str_contains($name, '/')) {
            return null;
        }

        return $name;
    }

    private function merge(mixed $current, mixed $next): mixed
    {
        if (Undefined::isDefault($current)) {
            return $next;
        }

        if (Undefined::isDefault($next)) {
            return $current;
        }

        if ($current instanceof stdClass) {
            $current = (array) $current;
        }

        if ($next instanceof stdClass) {
            $next = (array) $next;
        }

        if (!is_array($current) || !is_array($next)) {
            return $next;
        }

        if ([] === $current) {
            return $next;
        }

        if ([] === $next) {
            return $current;
        }

        if (array_is_list($current) || array_is_list($next)) {
            return array_is_list($next) ? $next : $current;
        }

        foreach ($next as $key => $value) {
            $current[$key] = array_key_exists($key, $current)
                ? $this->merge($current[$key], $value)
                : $value;
        }

        return $current;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalize($item);
            }

            return $normalized;
        }

        return $value;
    }
}
