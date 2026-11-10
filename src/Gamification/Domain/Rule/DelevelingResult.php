<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Rule;

/**
 * Resultado da aplicação de um ou mais períodos de inatividade.
 */
final readonly class DelevelingResult
{
    public function __construct(
        public int $xpBalance,
        public int $level,
        public int $xpLost,
        public int $levelsLost,
    ) {
    }

    public function changed(): bool
    {
        return $this->xpLost > 0 || $this->levelsLost > 0;
    }
}
