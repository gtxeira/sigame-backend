<?php

declare(strict_types=1);

namespace App\Groups\Infrastructure\Persistence\ORM\Type;

use App\Groups\Domain\Group\ValueObject\OwnershipTransferId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type para o Value Object OwnershipTransferId.
 */
final class OwnershipTransferIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?OwnershipTransferId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return OwnershipTransferId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof OwnershipTransferId) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'ownership_transfer_id';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
