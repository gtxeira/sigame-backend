<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\GetGamificationRules;

/**
 * Curva de níveis da gamificação.
 */
final readonly class LevelsRulesView
{
    /**
     * @param list<array{level: int, thresholdXp: int, xpForNextLevel: int|null}> $curve
     */
    public function __construct(
        public int $minLevel,
        public int $maxLevel,
        public array $curve,
    ) {
    }
}
