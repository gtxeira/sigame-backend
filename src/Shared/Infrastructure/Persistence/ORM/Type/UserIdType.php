<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\ORM\Type;

use App\Identity\Domain\ValueObject\UserId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type para o Value Object UserId.
 *
 * Converte entre UserId (Domain) e UUID string (banco de dados).
 */
final class UserIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return UserId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof UserId) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'user_id';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
