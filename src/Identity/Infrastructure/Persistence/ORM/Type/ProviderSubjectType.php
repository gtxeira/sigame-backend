<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\ORM\Type;

use App\Identity\Domain\User\ValueObject\ProviderSubject;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type para o Value Object ProviderSubject.
 */
final class ProviderSubjectType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(255)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ProviderSubject
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return ProviderSubject::fromString((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof ProviderSubject) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return 'provider_subject';
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
