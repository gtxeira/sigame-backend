<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Xp\Entity;

use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Rule\GamificationRules;
use App\Gamification\Domain\Xp\ValueObject\XpEventId;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lançamento append-only do ledger de XP.
 *
 * Estornos marcam o lançamento original como revertido e criam um novo
 * lançamento negativo. O `idempotency_key` é único apenas entre lançamentos
 * ativos, permitindo reaward depois de um estorno.
 */
#[ORM\Entity]
#[ORM\Table(name: 'xp_events')]
#[ORM\Index(name: 'idx_xp_events_user_created', columns: ['user_id', 'created_at'])]
#[ORM\Index(name: 'idx_xp_events_user_activity_date', columns: ['user_id', 'activity_date'])]
#[ORM\Index(name: 'idx_xp_events_activity', columns: ['activity_id'])]
final class XpEvent
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'xp_event_id', unique: true)]
        private readonly XpEventId $id,

        #[ORM\Column(type: 'player_id')]
        private readonly PlayerId $userId,

        #[ORM\Column(type: 'string', length: 40, enumType: XpKind::class)]
        private readonly XpKind $kind,

        #[ORM\Column(type: 'integer')]
        private readonly int $amount,

        #[ORM\Column(type: 'string', length: 40, nullable: true)]
        private readonly ?string $sourceType,

        #[ORM\Column(type: 'string', length: 64, nullable: true)]
        private readonly ?string $sourceId,

        #[ORM\Column(type: 'string', length: 64, nullable: true)]
        private readonly ?string $activityId,

        #[ORM\Column(type: 'string', length: 10, nullable: true)]
        private readonly ?string $activityDate,

        #[ORM\Column(type: 'string', length: 10, nullable: true)]
        private readonly ?string $weekKey,

        #[ORM\Column(type: 'string', length: 32)]
        private readonly string $ruleVersion,

        /** @var array<string, mixed>|null */
        #[ORM\Column(type: 'json', nullable: true)]
        private readonly ?array $metadata,

        #[ORM\Column(type: 'string', length: 191, unique: true)]
        private readonly string $idempotencyKey,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $reversedAt,

        #[ORM\Column(type: 'xp_event_id', nullable: true)]
        private readonly ?XpEventId $reversalOfId,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public static function record(
        PlayerId $userId,
        XpKind $kind,
        int $amount,
        string $idempotencyKey,
        ?string $sourceType = null,
        ?string $sourceId = null,
        ?string $activityId = null,
        ?string $activityDate = null,
        ?string $weekKey = null,
        ?array $metadata = null,
        ?XpEventId $reversalOfId = null,
        ?Instant $createdAt = null,
    ): self {
        return new self(
            id: XpEventId::generate(),
            userId: $userId,
            kind: $kind,
            amount: $amount,
            sourceType: $sourceType,
            sourceId: $sourceId,
            activityId: $activityId,
            activityDate: $activityDate,
            weekKey: $weekKey,
            ruleVersion: GamificationRules::VERSION,
            metadata: $metadata,
            idempotencyKey: $idempotencyKey,
            reversedAt: null,
            reversalOfId: $reversalOfId,
            createdAt: $createdAt ?? Instant::now(),
        );
    }

    public function markReversed(Instant $at): void
    {
        $this->reversedAt = $at;
    }

    public function isActive(): bool
    {
        return null === $this->reversedAt;
    }

    public function id(): XpEventId
    {
        return $this->id;
    }

    public function userId(): PlayerId
    {
        return $this->userId;
    }

    public function kind(): XpKind
    {
        return $this->kind;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function sourceType(): ?string
    {
        return $this->sourceType;
    }

    public function sourceId(): ?string
    {
        return $this->sourceId;
    }

    public function activityId(): ?string
    {
        return $this->activityId;
    }

    public function activityDate(): ?string
    {
        return $this->activityDate;
    }

    public function weekKey(): ?string
    {
        return $this->weekKey;
    }

    public function ruleVersion(): string
    {
        return $this->ruleVersion;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metadata(): ?array
    {
        return $this->metadata;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function reversedAt(): ?Instant
    {
        return $this->reversedAt;
    }

    public function reversalOfId(): ?XpEventId
    {
        return $this->reversalOfId;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }
}
