<?php

declare(strict_types=1);

namespace App\Groups\Infrastructure\Persistence\ORM\Type;

use App\Groups\Domain\Group\ValueObject\RemovalReason;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;

/**
 * Doctrine Type para o Value Object RemovalReason.
 */
final class RemovalReasonType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RemovalReason
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $result = RemovalReason::fromString((string) $value);

        if ($result->isInvalid()) {
            throw new LogicException('Motivo de remoção inválido persistido no banco.');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof RemovalReason) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'removal_reason';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
