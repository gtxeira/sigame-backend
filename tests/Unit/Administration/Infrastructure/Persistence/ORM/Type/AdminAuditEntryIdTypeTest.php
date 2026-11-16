<?php

declare(strict_types=1);

use App\Administration\Domain\Audit\ValueObject\AdminAuditEntryId;
use App\Administration\Infrastructure\Persistence\ORM\Type\AdminAuditEntryIdType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

it('declares the admin audit entry id as uuid', function () {
    expect((new AdminAuditEntryIdType())->getSQLDeclaration([], new PostgreSQLPlatform()))->toBe('UUID');
});

it('converts the admin audit entry id to and from the database', function () {
    $type = new AdminAuditEntryIdType();
    $id = AdminAuditEntryId::generate();

    expect($type->convertToDatabaseValue($id, new PostgreSQLPlatform()))->toBe($id->value())
        ->and($type->convertToPHPValue($id->value(), new PostgreSQLPlatform()))->toBeInstanceOf(AdminAuditEntryId::class);
});
