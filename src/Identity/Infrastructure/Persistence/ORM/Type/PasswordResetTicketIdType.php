<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\ORM\Type;

use App\Identity\Domain\PasswordReset\ValueObject\PasswordResetTicketId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type para o Value Object PasswordResetTicketId.
 */
final class PasswordResetTicketIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PasswordResetTicketId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return PasswordResetTicketId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof PasswordResetTicketId) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'password_reset_ticket_id';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
