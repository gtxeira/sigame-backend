<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\ORM\Type;

use App\Shared\Domain\ValueObject\Email;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use RuntimeException;

/**
 * Doctrine Type para o Value Object Email.
 *
 * Converte entre Email (Domain) e string (banco de dados).
 */
final class EmailType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(255)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Email
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $result = Email::fromString($value);

        if ($result->isInvalid()) {
            throw new RuntimeException('Dados inválidos no banco de dados para o campo Email.');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof Email) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'email';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
