<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\ORM\Type;

use App\Identity\Domain\ValueObject\UserLevel;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type para o Value Object UserLevel.
 *
 * Converte entre UserLevel (Domain) e integer (banco de dados).
 */
final class UserLevelType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'INTEGER';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserLevel
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return UserLevel::fromInt((int) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof UserLevel) {
            return $value->value();
        }

        return (int) $value;
    }

    public function getName(): string
    {
        return 'user_level';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
