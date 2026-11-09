<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Entity;

use App\Activities\Domain\Activity\ValueObject\ActivityReason;
use App\Activities\Domain\Activity\ValueObject\ActivityRescheduleId;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Registro append-only de remarcação de atividade.
 */
#[ORM\Entity]
#[ORM\Table(name: 'activity_reschedules')]
#[ORM\Index(name: 'idx_activity_reschedules_activity', columns: ['activity_id'])]
final class ActivityReschedule
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'activity_reschedule_id', unique: true)]
        private ActivityRescheduleId $id,

        #[ORM\ManyToOne(targetEntity: Activity::class, inversedBy: 'reschedules')]
        #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Activity $activity,

        #[ORM\Column(type: 'activity_user_id')]
        private ActivityUserId $changedBy,

        #[ORM\Column(type: 'instant')]
        private Instant $previousStartsAt,

        #[ORM\Column(type: 'instant')]
        private Instant $previousEndsAt,

        #[ORM\Column(type: 'instant')]
        private Instant $newStartsAt,

        #[ORM\Column(type: 'instant')]
        private Instant $newEndsAt,

        #[ORM\Column(type: 'string', length: 500, nullable: true)]
        private ?string $reason,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $changedAt,
    ) {
    }

    public static function record(
        Activity $activity,
        ActivityUserId $changedBy,
        Instant $previousStartsAt,
        Instant $previousEndsAt,
        Instant $newStartsAt,
        Instant $newEndsAt,
        ?ActivityReason $reason,
    ): self {
        return new self(
            id: ActivityRescheduleId::generate(),
            activity: $activity,
            changedBy: $changedBy,
            previousStartsAt: $previousStartsAt,
            previousEndsAt: $previousEndsAt,
            newStartsAt: $newStartsAt,
            newEndsAt: $newEndsAt,
            reason: $reason?->value(),
            changedAt: Instant::now(),
        );
    }

    public function id(): ActivityRescheduleId
    {
        return $this->id;
    }

    public function activity(): Activity
    {
        return $this->activity;
    }

    public function changedBy(): ActivityUserId
    {
        return $this->changedBy;
    }

    public function previousStartsAt(): Instant
    {
        return $this->previousStartsAt;
    }

    public function previousEndsAt(): Instant
    {
        return $this->previousEndsAt;
    }

    public function newStartsAt(): Instant
    {
        return $this->newStartsAt;
    }

    public function newEndsAt(): Instant
    {
        return $this->newEndsAt;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function changedAt(): Instant
    {
        return $this->changedAt;
    }
}
