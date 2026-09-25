<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\ORM\Type;

use App\Shared\Domain\ValueObject\Cpf;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use RuntimeException;

/**
 * Doctrine Type para o Value Object Cpf.
 *
 * Converte entre Cpf (Domain) e string (banco de dados).
 */
final class CpfType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(11)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Cpf
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $result = Cpf::fromString($value);

        if ($result->isInvalid()) {
            throw new RuntimeException('Dados inválidos no banco de dados para o campo CPF.');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof Cpf) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'cpf';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
