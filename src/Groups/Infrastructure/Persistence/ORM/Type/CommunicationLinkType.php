<?php

declare(strict_types=1);

namespace App\Groups\Infrastructure\Persistence\ORM\Type;

use App\Groups\Domain\Group\ValueObject\CommunicationLink;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use LogicException;

/**
 * Doctrine Type para o Value Object CommunicationLink.
 */
final class CommunicationLinkType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CommunicationLink
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $result = CommunicationLink::fromString((string) $value);

        if ($result->isInvalid()) {
            throw new LogicException('Link de comunicação inválido persistido no banco.');
        }

        return $result->value();
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof CommunicationLink) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'group_communication_link';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
