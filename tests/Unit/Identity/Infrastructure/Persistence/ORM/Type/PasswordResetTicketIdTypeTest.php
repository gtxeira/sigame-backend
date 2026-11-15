<?php

declare(strict_types=1);

use App\Identity\Domain\PasswordReset\ValueObject\PasswordResetTicketId;
use App\Identity\Infrastructure\Persistence\ORM\Type\PasswordResetTicketIdType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

it('declares the password reset ticket id as uuid', function () {
    expect((new PasswordResetTicketIdType())->getSQLDeclaration([], new PostgreSQLPlatform()))->toBe('UUID');
});

it('converts the password reset ticket id to and from the database', function () {
    $type = new PasswordResetTicketIdType();
    $id = PasswordResetTicketId::generate();

    expect($type->convertToDatabaseValue($id, new PostgreSQLPlatform()))->toBe($id->value())
        ->and($type->convertToPHPValue($id->value(), new PostgreSQLPlatform()))->toBeInstanceOf(PasswordResetTicketId::class);
});
