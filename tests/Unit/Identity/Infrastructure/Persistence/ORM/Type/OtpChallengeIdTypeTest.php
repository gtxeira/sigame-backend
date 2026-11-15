<?php

declare(strict_types=1);

use App\Identity\Domain\Otp\ValueObject\OtpChallengeId;
use App\Identity\Infrastructure\Persistence\ORM\Type\OtpChallengeIdType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

it('declares the otp challenge id as uuid', function () {
    expect((new OtpChallengeIdType())->getSQLDeclaration([], new PostgreSQLPlatform()))->toBe('UUID');
});

it('converts the otp challenge id to and from the database', function () {
    $type = new OtpChallengeIdType();
    $id = OtpChallengeId::generate();

    expect($type->convertToDatabaseValue($id, new PostgreSQLPlatform()))->toBe($id->value())
        ->and($type->convertToPHPValue($id->value(), new PostgreSQLPlatform()))->toBeInstanceOf(OtpChallengeId::class);
});
