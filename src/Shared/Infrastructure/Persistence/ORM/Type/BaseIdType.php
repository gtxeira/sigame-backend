<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\ORM\Type;

use App\Shared\Domain\ValueObject\BaseId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type para o Value Object BaseId.
 *
 * Converte entre BaseId (Domain) e string (banco de dados).
 */
final class BaseIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BaseId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return BaseId::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof BaseId) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'uuid';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
