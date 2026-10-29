<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/**
 * Value Object para as modalidades esportivas da plataforma.
 */
enum Modality: string
{
    case WALKING = 'caminhada';
    case RUNNING = 'corrida';
    case CYCLING = 'ciclismo';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $modality): string => $modality->value, self::cases());
    }
}
