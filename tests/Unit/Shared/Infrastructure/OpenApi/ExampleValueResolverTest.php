<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\UserStatus;
use App\Shared\Infrastructure\OpenApi\ExampleValueResolver;
use OpenApi\Attributes as OA;
use OpenApi\Undefined;

function exampleResolver(): ExampleValueResolver
{
    return new ExampleValueResolver();
}

it('prefers the example over const, enum and default', function () {
    $schema = new OA\Schema(example: 'example', const: 'const', enum: ['enum'], default: 'default');

    expect(exampleResolver()->resolve($schema, []))->toBe('example');
});

it('uses const when there is no example', function () {
    $schema = new OA\Schema(const: UserStatus::SUSPENDED, enum: [UserStatus::ACTIVE], default: 'default');

    expect(exampleResolver()->resolve($schema, []))->toBe('SUSPENDED');
});

it('uses the first enum value when there is no example or const', function () {
    $schema = new OA\Schema(enum: [UserStatus::ACTIVE, UserStatus::SUSPENDED]);

    expect(exampleResolver()->resolve($schema, []))->toBe('ACTIVE');
});

it('uses the default when there is no example, const or enum', function () {
    $schema = new OA\Schema(default: 'default');

    expect(exampleResolver()->resolve($schema, []))->toBe('default');
});

it('normalizes enums recursively', function () {
    $schema = new OA\Schema(example: ['status' => UserStatus::ACTIVE]);

    expect(exampleResolver()->resolve($schema, []))->toBe(['status' => 'ACTIVE']);
});

it('resolves a component ref', function () {
    $components = [
        'User' => new OA\Schema(
            properties: [
                new OA\Property(property: 'id', example: 'uuid'),
                new OA\Property(property: 'status', example: UserStatus::ACTIVE),
            ],
        ),
    ];

    $schema = new OA\Schema(ref: '#/components/schemas/User');

    expect(exampleResolver()->resolve($schema, $components))->toBe([
        'id' => 'uuid',
        'status' => 'ACTIVE',
    ]);
});

it('merges allOf branches', function () {
    $components = [
        'Envelope' => new OA\Schema(
            properties: [
                new OA\Property(property: 'data', description: 'Preenchido pelo ramo seguinte.'),
                new OA\Property(property: 'meta', properties: [new OA\Property(property: 'timestamp', example: 'now')], type: 'object'),
            ],
        ),
        'User' => new OA\Schema(
            properties: [new OA\Property(property: 'id', example: 'uuid')],
        ),
    ];

    $schema = new OA\Schema(allOf: [
        new OA\Schema(ref: '#/components/schemas/Envelope'),
        new OA\Schema(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')]),
    ]);

    expect(exampleResolver()->resolve($schema, $components))->toBe([
        'meta' => ['timestamp' => 'now'],
        'data' => ['id' => 'uuid'],
    ]);
});

it('builds an example for inline properties', function () {
    $schema = new OA\Schema(
        type: 'object',
        properties: [
            new OA\Property(property: 'status', example: 'UP'),
            new OA\Property(
                property: 'checks',
                type: 'object',
                properties: [new OA\Property(property: 'database', example: 'UP')],
            ),
        ],
    );

    expect(exampleResolver()->resolve($schema, []))->toBe([
        'status' => 'UP',
        'checks' => ['database' => 'UP'],
    ]);
});

it('uses a single item for array schemas', function () {
    $schema = new OA\Schema(
        type: 'array',
        items: new OA\Items(type: 'object', properties: [new OA\Property(property: 'id', example: 'uuid')]),
    );

    expect(exampleResolver()->resolve($schema, []))->toBe([['id' => 'uuid']]);
});

it('ignores writeOnly properties', function () {
    $schema = new OA\Schema(
        type: 'object',
        properties: [
            new OA\Property(property: 'email', example: 'user@example.com'),
            new OA\Property(property: 'password', writeOnly: true, example: 'secret'),
        ],
    );

    expect(exampleResolver()->resolve($schema, []))->toBe(['email' => 'user@example.com']);
});

it('returns an empty object for object schemas without properties', function () {
    $schema = new OA\Schema(type: 'object');

    expect(exampleResolver()->resolve($schema, []))->toBeInstanceOf(stdClass::class);
});

it('returns an empty list for array schemas without items', function () {
    $schema = new OA\Schema(type: 'array');

    expect(exampleResolver()->resolve($schema, []))->toBe([]);
});

it('returns undefined when there is nothing to derive', function () {
    $schema = new OA\Schema(type: 'string');

    expect(Undefined::isDefault(exampleResolver()->resolve($schema, [])))->toBeTrue();
});

it('returns null for nullable schemas without a value', function () {
    $schema = new OA\Schema(nullable: true);

    expect(exampleResolver()->resolve($schema, []))->toBeNull();
});

it('stops at a cyclic ref', function () {
    $components = [
        'A' => new OA\Schema(ref: '#/components/schemas/B'),
        'B' => new OA\Schema(ref: '#/components/schemas/A'),
    ];

    expect(Undefined::isDefault(exampleResolver()->resolve($components['A'], $components)))->toBeTrue();
});
