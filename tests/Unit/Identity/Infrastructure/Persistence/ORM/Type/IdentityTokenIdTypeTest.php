<?php

declare(strict_types=1);

use App\Identity\Domain\IdentityToken\ValueObject\IdentityTokenId;
use App\Identity\Infrastructure\Persistence\ORM\Type\IdentityTokenIdType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

it('declares a uuid column compatible with the platform', function () {
    expect((new IdentityTokenIdType())->getSQLDeclaration([], new PostgreSQLPlatform()))->toBe('UUID');
});

it('converts null to null', function () {
    $type = new IdentityTokenIdType();
    $platform = new PostgreSQLPlatform();

    expect($type->convertToDatabaseValue(null, $platform))->toBeNull()
        ->and($type->convertToPHPValue(null, $platform))->toBeNull();
});

it('round-trips an identity token id', function () {
    $type = new IdentityTokenIdType();
    $platform = new PostgreSQLPlatform();
    $id = IdentityTokenId::generate();

    $restored = $type->convertToPHPValue(
        $type->convertToDatabaseValue($id, $platform),
        $platform,
    );

    expect($restored?->equals($id))->toBeTrue();
});
