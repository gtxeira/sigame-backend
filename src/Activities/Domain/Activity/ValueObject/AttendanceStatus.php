<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\ValueObject;

enum AttendanceStatus: string
{
    case ENROLLED = 'enrolled';
    case CHECKED_IN = 'checked_in';
    case VALIDATED = 'validated';
    case CANCELLED = 'cancelled';

    public function isActive(): bool
    {
        return self::ENROLLED === $this || self::CHECKED_IN === $this || self::VALIDATED === $this;
    }
}
