<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Entity;

use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Domain\Activity\ValueObject\AttendanceId;
use App\Activities\Domain\Activity\ValueObject\AttendanceStatus;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\Mapping as ORM;

/**
 * Episódio de inscrição e presença de um usuário em uma atividade.
 *
 * Cancelamentos preservam o episódio; uma nova inscrição cria outro. O índice
 * único parcial garante apenas um episódio ativo por par atividade/usuário.
 */
#[ORM\Entity]
#[ORM\Table(name: 'activity_attendances')]
#[ORM\UniqueConstraint(
    name: 'uniq_activity_attendances_active',
    columns: ['activity_id', 'user_id'],
    options: ['where' => "(status IN ('enrolled', 'checked_in', 'validated'))"],
)]
#[ORM\Index(name: 'idx_activity_attendances_user_status', columns: ['user_id', 'status'])]
#[ORM\Index(name: 'idx_activity_attendances_activity_status', columns: ['activity_id', 'status'])]
final class Attendance
{
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'attendance_id', unique: true)]
        private AttendanceId $id,

        #[ORM\ManyToOne(targetEntity: Activity::class, inversedBy: 'attendances')]
        #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private Activity $activity,

        #[ORM\Column(type: 'activity_user_id')]
        private ActivityUserId $userId,

        #[ORM\Column(type: 'string', length: 20, enumType: AttendanceStatus::class)]
        private AttendanceStatus $status,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $enrolledAt,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $checkedInAt,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $validatedAt,

        #[ORM\Column(type: 'activity_user_id', nullable: true)]
        private ?ActivityUserId $validatedBy,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $cancelledAt,

        #[ORM\Column(type: 'activity_user_id', nullable: true)]
        private ?ActivityUserId $cancelledBy,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $createdAt,

        #[ORM\Column(type: 'instant')]
        private Instant $updatedAt,
    ) {
    }

    public static function enrolled(Activity $activity, ActivityUserId $userId): self
    {
        $now = Instant::now();

        return new self(
            id: AttendanceId::generate(),
            activity: $activity,
            userId: $userId,
            status: AttendanceStatus::ENROLLED,
            enrolledAt: $now,
            checkedInAt: null,
            validatedAt: null,
            validatedBy: null,
            cancelledAt: null,
            cancelledBy: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function checkIn(Instant $now): void
    {
        if (AttendanceStatus::CHECKED_IN === $this->status || AttendanceStatus::VALIDATED === $this->status) {
            return;
        }

        $this->status = AttendanceStatus::CHECKED_IN;
        $this->checkedInAt = $now;
        $this->touch();
    }

    public function validate(ActivityUserId $actor, Instant $now): void
    {
        if (AttendanceStatus::VALIDATED === $this->status) {
            return;
        }

        $this->status = AttendanceStatus::VALIDATED;
        $this->validatedAt = $now;
        $this->validatedBy = $actor;
        $this->touch();
    }

    public function cancel(Instant $now, ?ActivityUserId $actor = null): void
    {
        if (AttendanceStatus::CANCELLED === $this->status) {
            return;
        }

        $this->status = AttendanceStatus::CANCELLED;
        $this->cancelledAt = $now;
        $this->cancelledBy = $actor;
        $this->touch();
    }

    public function id(): AttendanceId
    {
        return $this->id;
    }

    public function activity(): Activity
    {
        return $this->activity;
    }

    public function userId(): ActivityUserId
    {
        return $this->userId;
    }

    public function status(): AttendanceStatus
    {
        return $this->status;
    }

    public function enrolledAt(): Instant
    {
        return $this->enrolledAt;
    }

    public function checkedInAt(): ?Instant
    {
        return $this->checkedInAt;
    }

    public function validatedAt(): ?Instant
    {
        return $this->validatedAt;
    }

    public function validatedBy(): ?ActivityUserId
    {
        return $this->validatedBy;
    }

    public function cancelledAt(): ?Instant
    {
        return $this->cancelledAt;
    }

    public function cancelledBy(): ?ActivityUserId
    {
        return $this->cancelledBy;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = Instant::now();
    }
}
