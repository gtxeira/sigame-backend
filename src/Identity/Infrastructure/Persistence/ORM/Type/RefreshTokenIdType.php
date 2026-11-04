<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\ORM\Type;

use App\Identity\Domain\RefreshToken\ValueObject\RefreshTokenId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type para o Value Object RefreshTokenId.
 */
final class RefreshTokenIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RefreshTokenId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return RefreshTokenId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof RefreshTokenId) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'refresh_token_id';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
