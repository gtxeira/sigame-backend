<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Achievement\Entity;

use App\Gamification\Domain\Achievement\ValueObject\AchievementCode;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Conquista desbloqueada por um usuário.
 *
 * A chave é composta por usuário e código: cada marco é registrado uma única
 * vez e permanece mesmo que o nível caia depois.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_achievements')]
final class UserAchievement
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'player_id')]
        private readonly PlayerId $userId,

        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 40, enumType: AchievementCode::class)]
        private readonly AchievementCode $code,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $achievedAt,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $celebratedAt,
    ) {
    }

    public static function unlock(
        PlayerId $userId,
        AchievementCode $code,
        ?Instant $achievedAt = null,
    ): self {
        return new self(
            userId: $userId,
            code: $code,
            achievedAt: $achievedAt ?? Instant::now(),
            celebratedAt: null,
        );
    }

    /**
     * Marca a comemoração como exibida. Retorna se houve transição.
     */
    public function celebrate(Instant $at): bool
    {
        if (null !== $this->celebratedAt) {
            return false;
        }

        $this->celebratedAt = $at;

        return true;
    }

    public function userId(): PlayerId
    {
        return $this->userId;
    }

    public function code(): AchievementCode
    {
        return $this->code;
    }

    public function achievedAt(): Instant
    {
        return $this->achievedAt;
    }

    public function celebratedAt(): ?Instant
    {
        return $this->celebratedAt;
    }

    public function isCelebrated(): bool
    {
        return null !== $this->celebratedAt;
    }
}
