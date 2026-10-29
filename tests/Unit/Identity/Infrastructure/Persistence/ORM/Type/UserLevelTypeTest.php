<?php

declare(strict_types=1);

use App\Identity\Domain\User\ValueObject\UserLevel;
use App\Identity\Infrastructure\Persistence\ORM\Type\UserLevelType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

it('declares an integer column compatible with the platform', function () {
    $type = new UserLevelType();

    expect($type->getSQLDeclaration([], new PostgreSQLPlatform()))->toBe('INT');
});

it('round-trips a user level', function () {
    $type = new UserLevelType();
    $platform = new PostgreSQLPlatform();
    $level = UserLevel::fromInt(2)->value();

    $restored = $type->convertToPHPValue(
        $type->convertToDatabaseValue($level, $platform),
        $platform,
    );

    expect($restored?->equals($level))->toBeTrue();
});
