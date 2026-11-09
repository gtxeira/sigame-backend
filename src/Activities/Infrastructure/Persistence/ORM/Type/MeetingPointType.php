<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Persistence\ORM\Type;

use App\Activities\Domain\Activity\ValueObject\MeetingPoint;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;

final class MeetingPointType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?MeetingPoint
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $result = MeetingPoint::fromString((string) $value);

        if ($result->isInvalid()) {
            throw new LogicException('Ponto de encontro inválido persistido no banco.');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof MeetingPoint ? $value->value() : (null === $value ? null : (string) $value);
    }

    public function getName(): string
    {
        return 'meeting_point';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
