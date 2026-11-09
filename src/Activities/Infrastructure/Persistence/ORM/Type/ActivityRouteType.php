<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Persistence\ORM\Type;

use App\Activities\Domain\Activity\ValueObject\ActivityRoute;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;

final class ActivityRouteType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ActivityRoute
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $result = ActivityRoute::fromString((string) $value);

        if ($result->isInvalid()) {
            throw new LogicException('Trajeto de atividade inválido persistido no banco.');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof ActivityRoute ? $value->value() : (null === $value ? null : (string) $value);
    }

    public function getName(): string
    {
        return 'activity_route';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
