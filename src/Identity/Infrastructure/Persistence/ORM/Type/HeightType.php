<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\ORM\Type;

use App\Identity\Domain\User\ValueObject\Height;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use RuntimeException;

/**
 * Doctrine Type para o Value Object Height.
 *
 * Converte entre Height (Domain) e centímetros (integer) no banco de dados.
 */
final class HeightType extends Type
{
    public const string NAME = 'height';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Height
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $result = Height::fromCentimeters((int) $value);

        if ($result->isInvalid()) {
            throw new RuntimeException('Dados inválidos no banco de dados para o campo height_cm (Height).');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof Height) {
            return $value->centimeters();
        }

        return (int) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
