<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\ORM\Type;

use App\Shared\Domain\ValueObject\BirthDate;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use RuntimeException;

/**
 * Doctrine Type para o Value Object BirthDate.
 *
 * Converte entre BirthDate (Domain) e DATE (banco de dados).
 */
final class BirthDateType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BirthDate
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $normalized = $value instanceof DateTimeInterface
            ? $value->format(BirthDate::STORAGE_FORMAT)
            : (string) $value;

        $result = BirthDate::fromString($normalized);

        if ($result->isInvalid()) {
            throw new RuntimeException('Dados inválidos no banco de dados para o campo de data de nascimento.');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof BirthDate) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'birth_date';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
