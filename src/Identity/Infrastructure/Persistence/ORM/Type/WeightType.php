<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\ORM\Type;

use App\Identity\Domain\User\ValueObject\Weight;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use RuntimeException;

/**
 * Doctrine Type para o Value Object Weight.
 *
 * Converte entre Weight (Domain) e quilogramas (float) no banco de dados.
 */
final class WeightType extends Type
{
    public const string NAME = 'weight';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getFloatDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Weight
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $result = Weight::fromKilograms((float) $value);

        if ($result->isInvalid()) {
            throw new RuntimeException('Dados inválidos no banco de dados para o campo weight_kg (Weight).');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?float
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof Weight) {
            return $value->kilograms();
        }

        return (float) $value;
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
