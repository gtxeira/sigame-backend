<?php

declare(strict_types=1);

namespace App\Groups\Infrastructure\Persistence\ORM\Type;

use App\Groups\Domain\Group\ValueObject\GroupDescription;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;

/**
 * Doctrine Type para o Value Object GroupDescription.
 */
final class GroupDescriptionType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?GroupDescription
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $result = GroupDescription::fromString((string) $value);

        if ($result->isInvalid()) {
            throw new LogicException('Descrição de grupo inválida persistida no banco.');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof GroupDescription) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'group_description';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
