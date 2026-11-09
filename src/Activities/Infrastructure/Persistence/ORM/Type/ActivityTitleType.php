<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Persistence\ORM\Type;

use App\Activities\Domain\Activity\ValueObject\ActivityTitle;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;

final class ActivityTitleType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ActivityTitle
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $result = ActivityTitle::fromString((string) $value);

        if ($result->isInvalid()) {
            throw new LogicException('Título de atividade inválido persistido no banco.');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof ActivityTitle ? $value->value() : (null === $value ? null : (string) $value);
    }

    public function getName(): string
    {
        return 'activity_title';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
