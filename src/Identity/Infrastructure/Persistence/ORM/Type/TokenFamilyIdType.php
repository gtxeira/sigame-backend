<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\ORM\Type;

use App\Identity\Domain\RefreshToken\ValueObject\TokenFamilyId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type para o Value Object TokenFamilyId.
 */
final class TokenFamilyIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TokenFamilyId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return TokenFamilyId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof TokenFamilyId) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'token_family_id';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
