<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\Weight;
use App\Identity\Infrastructure\Persistence\ORM\Type\WeightType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

it('declares a floating point column compatible with the platform', function () {
    $type = new WeightType();

    expect($type->getSQLDeclaration([], new PostgreSQLPlatform()))->toBe('DOUBLE PRECISION');
});

it('converts null to null', function () {
    $type = new WeightType();
    $platform = new PostgreSQLPlatform();

    expect($type->convertToDatabaseValue(null, $platform))->toBeNull()
        ->and($type->convertToPHPValue(null, $platform))->toBeNull();
});

it('round-trips a weight', function () {
    $type = new WeightType();
    $platform = new PostgreSQLPlatform();
    $weight = Weight::fromKilograms(75.5)->value();

    $restored = $type->convertToPHPValue(
        $type->convertToDatabaseValue($weight, $platform),
        $platform,
    );

    expect($restored?->equals($weight))->toBeTrue();
});

it('rejects an invalid weight read from the database', function () {
    (new WeightType())->convertToPHPValue(Weight::MIN_KILOGRAMS - 0.1, new PostgreSQLPlatform());
})->throws(RuntimeException::class);
