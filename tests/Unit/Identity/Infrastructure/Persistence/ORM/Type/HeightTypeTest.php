<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\Height;
use App\Identity\Infrastructure\Persistence\ORM\Type\HeightType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

it('declares an integer column compatible with the platform', function () {
    $type = new HeightType();

    expect($type->getSQLDeclaration([], new PostgreSQLPlatform()))->toBe('INT');
});

it('converts null to null', function () {
    $type = new HeightType();
    $platform = new PostgreSQLPlatform();

    expect($type->convertToDatabaseValue(null, $platform))->toBeNull()
        ->and($type->convertToPHPValue(null, $platform))->toBeNull();
});

it('round-trips a height', function () {
    $type = new HeightType();
    $platform = new PostgreSQLPlatform();
    $height = Height::fromCentimeters(178)->value();

    $restored = $type->convertToPHPValue(
        $type->convertToDatabaseValue($height, $platform),
        $platform,
    );

    expect($restored?->equals($height))->toBeTrue();
});

it('rejects an invalid height read from the database', function () {
    (new HeightType())->convertToPHPValue(Height::MIN_CENTIMETERS - 1, new PostgreSQLPlatform());
})->throws(RuntimeException::class);
