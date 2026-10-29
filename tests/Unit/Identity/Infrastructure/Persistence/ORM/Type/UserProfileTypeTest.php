<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\UserProfile;
use App\Identity\Infrastructure\Persistence\ORM\Type\UserProfileType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

it('declares a json column', function () {
    $type = new UserProfileType();

    expect($type->getSQLDeclaration([], new PostgreSQLPlatform()))->toBe('JSON');
});

it('converts null to null', function () {
    $type = new UserProfileType();
    $platform = new PostgreSQLPlatform();

    expect($type->convertToDatabaseValue(null, $platform))->toBeNull()
        ->and($type->convertToPHPValue(null, $platform))->toBeNull();
});

it('round-trips a complete user profile', function () {
    $type = new UserProfileType();
    $platform = new PostgreSQLPlatform();
    $profile = UserProfile::create(
        weight: 75.5,
        height: 1.78,
        photoUrl: 'https://example.com/photo.jpg',
        modalities: ['caminhada', 'corrida'],
        primaryModality: 'caminhada',
    );

    $databaseValue = $type->convertToDatabaseValue($profile, $platform);
    $restored = $type->convertToPHPValue($databaseValue, $platform);

    expect($databaseValue)->toBeJson()
        ->and($restored?->weight())->toBe(75.5)
        ->and($restored?->height())->toBe(1.78)
        ->and($restored?->photoUrl())->toBe('https://example.com/photo.jpg')
        ->and($restored?->modalities())->toBe(['caminhada', 'corrida'])
        ->and($restored?->primaryModality())->toBe('caminhada')
        ->and($restored?->equals($profile))->toBeTrue();
});

it('accepts a profile with only optional fields omitted', function () {
    $type = new UserProfileType();
    $platform = new PostgreSQLPlatform();
    $profile = UserProfile::create();

    $restored = $type->convertToPHPValue(
        $type->convertToDatabaseValue($profile, $platform),
        $platform,
    );

    expect($restored?->equals($profile))->toBeTrue();
});

it('rejects invalid json', function () {
    (new UserProfileType())->convertToPHPValue('{invalid', new PostgreSQLPlatform());
})->throws(RuntimeException::class);

it('rejects a non-profile value when writing', function () {
    (new UserProfileType())->convertToDatabaseValue('invalid', new PostgreSQLPlatform());
})->throws(RuntimeException::class);
