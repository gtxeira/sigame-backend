<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Rule;

use App\Shared\Domain\ValueObject\Instant;

/**
 * Aplica a perda de progresso por inatividade.
 *
 * A cada período completo de 2 dias após a tolerância, 2% da barra do nível
 * atual são consumidos. Ao cruzar 0%, o usuário cai um nível e a barra do novo
 * nível começa em 98%. O piso é o nível 2.
 */
final readonly class DelevelingPolicy
{
    public function __construct(
        private LevelCurve $levelCurve,
    ) {
    }

    public function completePeriods(
        ?Instant $lastValidatedActivityAt,
        Instant $now,
        int $graceDays = GamificationRules::DECAY_GRACE_DAYS,
        int $periodDays = GamificationRules::DECAY_PERIOD_DAYS,
    ): int {
        if (null === $lastValidatedActivityAt) {
            return 0;
        }

        $elapsed = $now->timestamp() - $lastValidatedActivityAt->timestamp();
        $elapsed -= $graceDays * 86400;

        if ($elapsed <= 0) {
            return 0;
        }

        return intdiv($elapsed, $periodDays * 86400);
    }

    public function apply(int $xpBalance, int $level, int $periods): DelevelingResult
    {
        $periods = max(0, $periods);
        $originalXp = $xpBalance;
        $originalLevel = $level;

        if ($periods > 0 && $level > GamificationRules::DECAY_FLOOR_LEVEL) {
            for ($period = 0; $period < $periods; ++$period) {
                if ($level <= GamificationRules::DECAY_FLOOR_LEVEL
                    && $xpBalance <= $this->levelCurve->threshold(GamificationRules::DECAY_FLOOR_LEVEL)
                ) {
                    break;
                }

                $span = $this->levelCurve->barSpan($level);
                $penalty = max(1, (int) floor($span * GamificationRules::DECAY_PERCENT / 100));
                $xpBalance -= $penalty;

                $newLevel = $this->levelCurve->levelFor($xpBalance);

                if ($newLevel < $level) {
                    $level = max(GamificationRules::DECAY_FLOOR_LEVEL, $newLevel);

                    if ($level === GamificationRules::DECAY_FLOOR_LEVEL) {
                        $xpBalance = max(
                            $this->levelCurve->threshold(GamificationRules::DECAY_FLOOR_LEVEL),
                            $xpBalance,
                        );
                    } else {
                        $xpBalance = $this->levelCurve->xpForBar($level, 0.98);
                    }
                }
            }
        }

        $floorXp = $this->levelCurve->threshold(GamificationRules::DECAY_FLOOR_LEVEL);
        $xpBalance = max($floorXp, $xpBalance);
        $level = max(GamificationRules::DECAY_FLOOR_LEVEL, $this->levelCurve->levelFor($xpBalance));

        return new DelevelingResult(
            xpBalance: $xpBalance,
            level: $level,
            xpLost: max(0, $originalXp - $xpBalance),
            levelsLost: max(0, $originalLevel - $level),
        );
    }
}
