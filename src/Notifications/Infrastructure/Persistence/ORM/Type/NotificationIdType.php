<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence\ORM\Type;

use App\Notifications\Domain\Notification\ValueObject\NotificationId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class NotificationIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?NotificationId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return NotificationId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return $value instanceof NotificationId ? $value->value() : (string) $value;
    }

    public function getName(): string
    {
        return 'notification_id';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
