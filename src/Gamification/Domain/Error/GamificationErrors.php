<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Error;

use App\Shared\Domain\Error\DomainError;

/**
 * Fábricas de erros esperados do módulo Gamification.
 */
final class GamificationErrors
{
    public static function achievementNotFound(): DomainError
    {
        return new DomainError(
            code: GamificationErrorCode::AchievementNotFound->value,
            message: 'Conquista não encontrada.',
            kind: GamificationErrorCode::AchievementNotFound->kind(),
            field: 'code',
        );
    }
}
