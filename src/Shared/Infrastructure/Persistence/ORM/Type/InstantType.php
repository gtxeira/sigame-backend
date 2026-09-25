<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\ORM\Type;

use App\Shared\Domain\ValueObject\Instant;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use RuntimeException;

final class InstantType extends Type
{
    public const string NAME = 'instant';

    public function getSQLDeclaration(
        array $column,
        AbstractPlatform $platform,
    ): string {
        $column['precision'] = 6;

        return $platform->getDateTimeTzTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(
        mixed $value,
        AbstractPlatform $platform,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Instant) {
            throw new RuntimeException(
                sprintf(
                    'O tipo da instância esperada para o "$value" era %s, o recebido foi %s.',
                    Instant::class,
                    get_debug_type($value),
                ),
            );
        }

        return $value->toIsoStringWithMicroseconds();
    }

    /**
     * @throws DateMalformedStringException
     */
    public function convertToPHPValue(
        mixed $value,
        AbstractPlatform $platform,
    ): ?Instant {
        if ($value === null || $value instanceof Instant) {
            return $value;
        }

        if (!$value instanceof DateTimeImmutable) {
            $value = new DateTimeImmutable((string) $value);
        }

        return Instant::fromDateTime($value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
