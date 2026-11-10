<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence\ORM\Type;

use App\Notifications\Domain\Notification\ValueObject\RecipientId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class NotificationRecipientIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?RecipientId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return RecipientId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return $value instanceof RecipientId ? $value->value() : (string) $value;
    }

    public function getName(): string
    {
        return 'notification_recipient_id';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
