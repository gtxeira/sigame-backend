<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Error;

use App\Shared\Domain\Error\ErrorKind;

/**
 * Catálogo de erros públicos do módulo Gamification.
 */
enum GamificationErrorCode: string
{
    case AchievementNotFound = 'ACHIEVEMENT_NOT_FOUND';

    public function kind(): ErrorKind
    {
        return match ($this) {
            self::AchievementNotFound => ErrorKind::NotFound,
        };
    }
}
