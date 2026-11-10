<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\GetGamificationRules;

/**
 * Estado visual do foguinho semanal.
 */
final readonly class StreakStateView
{
    public function __construct(
        public string $value,
        public string $label,
        public string $description,
    ) {
    }
}
