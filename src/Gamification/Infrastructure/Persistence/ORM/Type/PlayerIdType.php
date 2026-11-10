<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Persistence\ORM\Type;

use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class PlayerIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PlayerId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return PlayerId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof PlayerId) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'player_id';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
