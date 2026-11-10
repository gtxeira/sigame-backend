<?php

declare(strict_types=1);

namespace App\Groups\Infrastructure\Persistence\ORM\Type;

use App\Groups\Domain\Group\ValueObject\GroupMemberLimit;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;

/**
 * Doctrine Type para o Value Object GroupMemberLimit.
 */
final class GroupMemberLimitType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?GroupMemberLimit
    {
        if (null === $value) {
            return null;
        }

        $result = GroupMemberLimit::fromInt((int) $value);

        if ($result->isInvalid()) {
            throw new LogicException('Limite de membros inválido persistido no banco.');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof GroupMemberLimit) {
            return $value->value();
        }

        return (int) $value;
    }

    public function getName(): string
    {
        return 'group_member_limit';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
