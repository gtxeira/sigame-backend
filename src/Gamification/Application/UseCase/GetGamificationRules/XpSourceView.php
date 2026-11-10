<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\GetGamificationRules;

/**
 * Fonte positiva de XP exibida no catálogo de regras.
 */
final readonly class XpSourceView
{
    public function __construct(
        public string $kind,
        public string $label,
        public string $description,
        public ?int $amount,
        public ?int $minAmount,
        public ?int $maxAmount,
    ) {
    }
}
