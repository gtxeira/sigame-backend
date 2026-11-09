<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

enum ActivityStatus: string
{
    case SCHEDULED = 'scheduled';
    case STARTED = 'started';
    case FINISHED = 'finished';
    case CANCELLED = 'cancelled';
    case INVALIDATED = 'invalidated';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function isTerminal(): bool
    {
        return self::CANCELLED === $this || self::INVALIDATED === $this;
    }

    public function isActive(): bool
    {
        return self::SCHEDULED === $this || self::STARTED === $this;
    }
}
