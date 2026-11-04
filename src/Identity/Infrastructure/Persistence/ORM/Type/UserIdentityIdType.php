<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\ORM\Type;

use App\Identity\Domain\User\ValueObject\UserIdentityId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type para o Value Object UserIdentityId.
 */
final class UserIdentityIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserIdentityId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return UserIdentityId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof UserIdentityId) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'user_identity_id';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
