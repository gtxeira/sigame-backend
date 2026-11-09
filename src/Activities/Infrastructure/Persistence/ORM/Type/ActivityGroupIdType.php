<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Persistence\ORM\Type;

use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ActivityGroupIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ActivityGroupId
    {
        return null === $value || '' === $value ? null : ActivityGroupId::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof ActivityGroupId ? $value->value() : (null === $value ? null : (string) $value);
    }

    public function getName(): string
    {
        return 'activity_group_id';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
