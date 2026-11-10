<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Persistence\ORM\Type;

use App\Gamification\Domain\Xp\ValueObject\XpEventId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class XpEventIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?XpEventId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return XpEventId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof XpEventId) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'xp_event_id';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
