<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\Instant;
use App\Shared\Infrastructure\Persistence\ORM\Type\InstantType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

it('declares a timestamp with microseconds and timezone on postgresql', function () {
    $type = new InstantType();

    expect($type->getSQLDeclaration([], new PostgreSQLPlatform()))
        ->toBe('TIMESTAMP(6) WITH TIME ZONE');
});

it('converts null to null', function () {
    $type = new InstantType();
    $platform = new PostgreSQLPlatform();

    expect($type->convertToDatabaseValue(null, $platform))->toBeNull()
        ->and($type->convertToPHPValue(null, $platform))->toBeNull();
});

it('round-trips an instant with microseconds', function () {
    $type = new InstantType();
    $platform = new PostgreSQLPlatform();
    $instant = Instant::now();

    $databaseValue = $type->convertToDatabaseValue($instant, $platform);
    $restored = $type->convertToPHPValue($databaseValue, $platform);

    expect($databaseValue)->toBe($instant->toIsoStringWithMicroseconds())
        ->and($restored?->equals($instant))->toBeTrue();
});

it('rejects a non-instant value when writing', function () {
    (new InstantType())->convertToDatabaseValue('invalid', new PostgreSQLPlatform());
})->throws(RuntimeException::class);
