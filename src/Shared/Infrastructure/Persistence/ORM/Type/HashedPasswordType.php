<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\ORM\Type;

use App\Identity\Domain\ValueObject\HashedPassword;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type para o Value Object HashedPassword.
 *
 * Converte entre HashedPassword (Domain) e string (banco de dados).
 */
final class HashedPasswordType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(255)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?HashedPassword
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return HashedPassword::fromHash((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof HashedPassword) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'hashed_password';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
