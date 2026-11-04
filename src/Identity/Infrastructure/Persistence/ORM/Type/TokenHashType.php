<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\ORM\Type;

use App\Identity\Domain\RefreshToken\ValueObject\TokenHash;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type para o Value Object TokenHash.
 */
final class TokenHashType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(64)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TokenHash
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return TokenHash::fromHash((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof TokenHash) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'token_hash';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
