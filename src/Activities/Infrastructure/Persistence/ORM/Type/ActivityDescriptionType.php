<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Persistence\ORM\Type;

use App\Activities\Domain\Activity\ValueObject\ActivityDescription;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;

final class ActivityDescriptionType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ActivityDescription
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $result = ActivityDescription::fromString((string) $value);

        if ($result->isInvalid()) {
            throw new LogicException('Descrição de atividade inválida persistida no banco.');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof ActivityDescription ? $value->value() : (null === $value ? null : (string) $value);
    }

    public function getName(): string
    {
        return 'activity_description';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
