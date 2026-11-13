<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Achievement\Service;

use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;

/**
 * Resolve quais conquistas o nível informado alcança.
 */
final readonly class AchievementCatalog
{
    /**
     * @return list<AchievementCode>
     */
    public function all(): array
    {
        return AchievementCode::cases();
    }

    /**
     * @return list<AchievementCode>
     */
    public function reachedAt(int $level): array
    {
        return array_filter(
            AchievementCode::cases(),
            static fn (AchievementCode $code): bool => $level >= $code->requiredLevel(),
        );
    }
}
