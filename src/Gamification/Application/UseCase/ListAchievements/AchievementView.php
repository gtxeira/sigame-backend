<?php

declare(strict_types=1);

namespace App\Gamification\Application\UseCase\ListAchievements;

use App\Gamification\Domain\Achievement\Entity\UserAchievement;
use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Conquista desbloqueada exibida pela API.
 */
final readonly class AchievementView
{
    public function __construct(
        public AchievementCode $code,
        public Instant $achievedAt,
        public ?Instant $celebratedAt,
    ) {
    }

    public static function fromEntity(UserAchievement $achievement): self
    {
        return new self(
            code: $achievement->code(),
            achievedAt: $achievement->achievedAt(),
            celebratedAt: $achievement->celebratedAt(),
        );
    }
}
