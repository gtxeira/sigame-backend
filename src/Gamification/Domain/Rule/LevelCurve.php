<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Rule;

/**
 * Curva triangular de níveis.
 *
 * T(n) = 50 * n * (n - 1). A barra do nível n é a distância até T(n + 1).
 */
final readonly class LevelCurve
{
    public function threshold(int $level): int
    {
        $level = max(GamificationRules::MIN_LEVEL, min(GamificationRules::MAX_LEVEL, $level));

        return 50 * $level * ($level - 1);
    }

    public function levelFor(int $xp): int
    {
        $level = GamificationRules::MIN_LEVEL;

        for ($candidate = GamificationRules::MIN_LEVEL; $candidate <= GamificationRules::MAX_LEVEL; ++$candidate) {
            if ($this->threshold($candidate) <= $xp) {
                $level = $candidate;
            }
        }

        return $level;
    }

    /**
     * XP necessário para percorrer a barra do nível.
     *
     * No nível 10 usa o último segmento (900) apenas para o cálculo de decay.
     */
    public function barSpan(int $level): int
    {
        $level = max(GamificationRules::MIN_LEVEL, min(GamificationRules::MAX_LEVEL, $level));

        if (GamificationRules::MAX_LEVEL === $level) {
            return $this->threshold(GamificationRules::MAX_LEVEL) - $this->threshold(GamificationRules::MAX_LEVEL - 1);
        }

        return $this->threshold($level + 1) - $this->threshold($level);
    }

    /**
     * Percentual da barra entre 0.0 e 1.0.
     */
    public function barPercent(int $level, int $xp): float
    {
        $level = max(GamificationRules::MIN_LEVEL, min(GamificationRules::MAX_LEVEL, $level));

        if (GamificationRules::MAX_LEVEL === $level) {
            return 1.0;
        }

        $start = $this->threshold($level);
        $span = $this->barSpan($level);

        if ($span <= 0) {
            return 1.0;
        }

        return max(0.0, min(1.0, ($xp - $start) / $span));
    }

    public function xpForBar(int $level, float $percent): int
    {
        $level = max(GamificationRules::MIN_LEVEL, min(GamificationRules::MAX_LEVEL, $level));
        $percent = max(0.0, min(1.0, $percent));

        return $this->threshold($level) + (int) round($this->barSpan($level) * $percent);
    }

    public function nextLevelXp(int $level): ?int
    {
        if ($level >= GamificationRules::MAX_LEVEL) {
            return null;
        }

        return $this->threshold($level + 1);
    }
}
