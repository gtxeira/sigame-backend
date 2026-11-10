<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\GetGamificationRules;

/**
 * Condições para uma atividade gerar XP.
 */
final readonly class ActivityEligibilityView
{
    public function __construct(
        public int $minimumCheckIns,
        public bool $requiresFinishedActivity,
        public bool $requiresValidatedAttendance,
        public bool $requiresXpEligibleEvent,
    ) {
    }
}
