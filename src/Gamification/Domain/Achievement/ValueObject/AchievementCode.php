<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Achievement\ValueObject;

use App\Gamification\Domain\Rule\GamificationRules;

/**
 * Catálogo estável de conquistas.
 *
 * O valor é contrato público com os clientes. Novos marcos entram como novos
 * cases, com o critério correspondente em `requiredLevel()`.
 */
enum AchievementCode: string
{
    case LENDA = 'LENDA';

    public function label(): string
    {
        return match ($this) {
            self::LENDA => 'Lenda',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LENDA => sprintf('Alcance o nível %d.', GamificationRules::MAX_LEVEL),
        };
    }

    public function requiredLevel(): int
    {
        return match ($this) {
            self::LENDA => GamificationRules::MAX_LEVEL,
        };
    }
}
