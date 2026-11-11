<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\BirthDate;
use App\Shared\Infrastructure\Persistence\ORM\Type\BirthDateType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

it('declares a date column compatible with the platform', function () {
    $type = new BirthDateType();

    expect($type->getSQLDeclaration([], new PostgreSQLPlatform()))->toBe('DATE');
});

it('converts null to null', function () {
    $type = new BirthDateType();
    $platform = new PostgreSQLPlatform();

    expect($type->convertToDatabaseValue(null, $platform))->toBeNull()
        ->and($type->convertToPHPValue(null, $platform))->toBeNull();
});

it('round-trips a birth date', function () {
    $type = new BirthDateType();
    $platform = new PostgreSQLPlatform();
    $birthDate = BirthDate::fromString('1990-05-12')->value();

    $restored = $type->convertToPHPValue(
        $type->convertToDatabaseValue($birthDate, $platform),
        $platform,
    );

    expect($restored?->equals($birthDate))->toBeTrue();
});

it('accepts a database date object', function () {
    $type = new BirthDateType();

    $restored = $type->convertToPHPValue(
        new DateTimeImmutable('1990-05-12 00:00:00'),
        new PostgreSQLPlatform(),
    );

    expect($restored?->value())->toBe('1990-05-12');
});

it('rejects an invalid birth date read from the database', function () {
    (new BirthDateType())->convertToPHPValue('1990-02-30', new PostgreSQLPlatform());
})->throws(RuntimeException::class);
