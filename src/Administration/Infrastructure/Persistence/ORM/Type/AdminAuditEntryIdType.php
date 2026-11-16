<?php

declare(strict_types=1);

namespace App\Administration\Infrastructure\Persistence\ORM\Type;

use App\Administration\Domain\Audit\ValueObject\AdminAuditEntryId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type para o Value Object AdminAuditEntryId.
 */
final class AdminAuditEntryIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AdminAuditEntryId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return AdminAuditEntryId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof AdminAuditEntryId) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'admin_audit_entry_id';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
