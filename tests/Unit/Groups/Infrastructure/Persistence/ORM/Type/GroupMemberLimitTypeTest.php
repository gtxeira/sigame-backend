<?php

declare(strict_types=1);

use App\Groups\Domain\Group\ValueObject\GroupMemberLimit;
use App\Groups\Infrastructure\Persistence\ORM\Type\GroupMemberLimitType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

it('declares an integer column compatible with the platform', function () {
    $type = new GroupMemberLimitType();

    expect($type->getSQLDeclaration([], new PostgreSQLPlatform()))->toBe('INT');
});

it('round-trips a member limit', function () {
    $type = new GroupMemberLimitType();
    $platform = new PostgreSQLPlatform();
    $limit = GroupMemberLimit::fromInt(8)->value();

    $restored = $type->convertToPHPValue(
        $type->convertToDatabaseValue($limit, $platform),
        $platform,
    );

    expect($restored?->equals($limit))->toBeTrue();
});

it('keeps null as unlimited', function () {
    $type = new GroupMemberLimitType();
    $platform = new PostgreSQLPlatform();

    expect($type->convertToDatabaseValue(null, $platform))->toBeNull()
        ->and($type->convertToPHPValue(null, $platform))->toBeNull();
});

it('rejects an invalid persisted member limit', function () {
    (new GroupMemberLimitType())->convertToPHPValue(2, new PostgreSQLPlatform());
})->throws(LogicException::class, 'Limite de membros inválido persistido no banco.');
