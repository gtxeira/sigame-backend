<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Entity;

use App\Activities\Domain\Activity\Policy\ActivityPolicy;
use App\Activities\Domain\Activity\ValueObject\ActivityDescription;
use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\ActivityReason;
use App\Activities\Domain\Activity\ValueObject\ActivityRoute;
use App\Activities\Domain\Activity\ValueObject\ActivityStatus;
use App\Activities\Domain\Activity\ValueObject\ActivityTitle;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Domain\Activity\ValueObject\ActivityWindow;
use App\Activities\Domain\Activity\ValueObject\AttendanceId;
use App\Activities\Domain\Activity\ValueObject\AttendanceStatus;
use App\Activities\Domain\Activity\ValueObject\EstimatedDistance;
use App\Activities\Domain\Activity\ValueObject\MeetingPoint;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Raiz do agregado de atividades de grupo.
 *
 * A atividade guarda a janela planejada, o ciclo de vida, as inscrições e o
 * histórico de remarcações. A autorização sobre o grupo é resolvida na
 * Application pela porta GroupAccessReaderInterface.
 */
#[ORM\Entity]
#[ORM\Table(name: 'activities')]
#[ORM\Index(name: 'idx_activities_group_starts_at', columns: ['group_id', 'starts_at'])]
#[ORM\Index(name: 'idx_activities_status_auto_close_at', columns: ['status', 'auto_close_at'])]
#[ORM\Index(name: 'idx_activities_created_by', columns: ['created_by'])]
final class Activity
{
    /** @var Collection<int, Attendance> */
    #[ORM\OneToMany(
        targetEntity: Attendance::class,
        mappedBy: 'activity',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $attendances;

    /** @var Collection<int, ActivityReschedule> */
    #[ORM\OneToMany(
        targetEntity: ActivityReschedule::class,
        mappedBy: 'activity',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    private Collection $reschedules;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'activity_id', unique: true)]
        private ActivityId $id,

        #[ORM\Column(type: 'activity_group_id')]
        private readonly ActivityGroupId $groupId,

        #[ORM\Column(type: 'activity_user_id')]
        private readonly ActivityUserId $createdBy,

        #[ORM\Column(type: 'activity_title', length: 100)]
        private ActivityTitle $title,

        #[ORM\Column(type: 'activity_description', length: 500, nullable: true)]
        private ?ActivityDescription $description,

        #[ORM\Column(type: 'instant')]
        private Instant $startsAt,

        #[ORM\Column(type: 'instant')]
        private Instant $endsAt,

        #[ORM\Column(type: 'integer', nullable: true)]
        private ?int $estimatedDurationMinutes,

        #[ORM\Column(type: 'meeting_point', length: 255)]
        private MeetingPoint $meetingPoint,

        #[ORM\Column(type: 'activity_route', length: 500)]
        private ActivityRoute $route,

        #[ORM\Column(type: 'integer')]
        private int $estimatedDistanceMeters,

        #[ORM\Column(type: 'string', length: 20, enumType: ActivityStatus::class)]
        private ActivityStatus $status,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $startedAt,

        #[ORM\Column(type: 'activity_user_id', nullable: true)]
        private ?ActivityUserId $startedBy,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $autoCloseAt,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $finishedAt,

        #[ORM\Column(type: 'activity_user_id', nullable: true)]
        private ?ActivityUserId $finishedBy,

        #[ORM\Column(type: 'boolean', options: ['default' => false])]
        private bool $finishedAutomatically,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $cancelledAt,

        #[ORM\Column(type: 'activity_user_id', nullable: true)]
        private ?ActivityUserId $cancelledBy,

        #[ORM\Column(type: 'boolean', options: ['default' => false])]
        private bool $cancelledAutomatically,

        #[ORM\Column(type: 'string', length: 500, nullable: true)]
        private ?string $cancellationReason,

        #[ORM\Column(type: 'instant', nullable: true)]
        private ?Instant $invalidatedAt,

        #[ORM\Column(type: 'activity_user_id', nullable: true)]
        private ?ActivityUserId $invalidatedBy,

        #[ORM\Column(type: 'string', length: 500, nullable: true)]
        private ?string $invalidationReason,

        #[ORM\Column(type: 'instant')]
        private readonly Instant $createdAt,

        #[ORM\Column(type: 'instant')]
        private Instant $updatedAt,
    ) {
        $this->attendances = new ArrayCollection();
        $this->reschedules = new ArrayCollection();
    }

    public static function create(
        ActivityGroupId $groupId,
        ActivityUserId $createdBy,
        ActivityTitle $title,
        ?ActivityDescription $description,
        ActivityWindow $window,
        MeetingPoint $meetingPoint,
        ActivityRoute $route,
        EstimatedDistance $estimatedDistance,
    ): self {
        $now = Instant::now();

        return new self(
            id: ActivityId::generate(),
            groupId: $groupId,
            createdBy: $createdBy,
            title: $title,
            description: $description,
            startsAt: $window->startsAt(),
            endsAt: $window->endsAt(),
            estimatedDurationMinutes: $window->estimatedDurationMinutes(),
            meetingPoint: $meetingPoint,
            route: $route,
            estimatedDistanceMeters: $estimatedDistance->meters(),
            status: ActivityStatus::SCHEDULED,
            startedAt: null,
            startedBy: null,
            autoCloseAt: null,
            finishedAt: null,
            finishedBy: null,
            finishedAutomatically: false,
            cancelledAt: null,
            cancelledBy: null,
            cancelledAutomatically: false,
            cancellationReason: null,
            invalidatedAt: null,
            invalidatedBy: null,
            invalidationReason: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * @return Result<$this>
     */
    public function updateDetails(
        ActivityTitle $title,
        ?ActivityDescription $description,
        MeetingPoint $meetingPoint,
        ActivityRoute $route,
        EstimatedDistance $estimatedDistance,
    ): Result {
        if (!$this->isScheduled()) {
            return Result::failure([$this->notScheduledError()]);
        }

        $this->title = $title;
        $this->description = $description;
        $this->meetingPoint = $meetingPoint;
        $this->route = $route;
        $this->estimatedDistanceMeters = $estimatedDistance->meters();
        $this->touch();

        return Result::success($this);
    }

    /**
     * @return Result<$this>
     */
    public function reschedule(
        ActivityWindow $window,
        ActivityUserId $actor,
        ?ActivityReason $reason,
        Instant $now,
    ): Result {
        if (!$this->isScheduled()) {
            return Result::failure([$this->notScheduledError()]);
        }

        if (!$window->startsAt()->isAfter($now)) {
            return Result::failure([ActivityErrors::windowInvalid(
                ActivityPolicy::MIN_ESTIMATED_DURATION_MINUTES,
                ActivityPolicy::MAX_ESTIMATED_DURATION_MINUTES,
            )]);
        }

        $this->reschedules->add(ActivityReschedule::record(
            activity: $this,
            changedBy: $actor,
            previousStartsAt: $this->startsAt,
            previousEndsAt: $this->endsAt,
            newStartsAt: $window->startsAt(),
            newEndsAt: $window->endsAt(),
            reason: $reason,
        ));

        $this->startsAt = $window->startsAt();
        $this->endsAt = $window->endsAt();
        $this->estimatedDurationMinutes = $window->estimatedDurationMinutes();
        $this->touch();

        return Result::success($this);
    }

    /**
     * @return Result<bool> `true` quando a transição ocorreu
     */
    public function cancel(
        ?ActivityUserId $actor,
        ?ActivityReason $reason,
        Instant $now,
        bool $automatic = false,
    ): Result {
        if (ActivityStatus::CANCELLED === $this->status) {
            return Result::success(false);
        }

        if (ActivityStatus::INVALIDATED === $this->status) {
            return Result::failure([ActivityErrors::invalidated()]);
        }

        if (ActivityStatus::SCHEDULED !== $this->status) {
            return Result::failure([ActivityErrors::notScheduled()]);
        }

        $this->status = ActivityStatus::CANCELLED;
        $this->cancelledAt = $now;
        $this->cancelledBy = $actor;
        $this->cancelledAutomatically = $automatic;
        $this->cancellationReason = $reason?->value();
        $this->touch();

        return Result::success(true);
    }

    /**
     * @return Result<bool> `true` quando a transição ocorreu
     */
    public function start(ActivityUserId $actor, Instant $now): Result
    {
        if (ActivityStatus::STARTED === $this->status) {
            return Result::success(false);
        }

        if (ActivityStatus::CANCELLED === $this->status) {
            return Result::failure([ActivityErrors::cancelled()]);
        }

        if (ActivityStatus::INVALIDATED === $this->status) {
            return Result::failure([ActivityErrors::invalidated()]);
        }

        if (ActivityStatus::SCHEDULED !== $this->status) {
            return Result::failure([ActivityErrors::notScheduled()]);
        }

        if ($now->isBefore(ActivityPolicy::startOpensAt($this->startsAt))
            || $now->isAfter(ActivityPolicy::startClosesAt($this->startsAt))
        ) {
            return Result::failure([ActivityErrors::startWindowClosed()]);
        }

        $this->status = ActivityStatus::STARTED;
        $this->startedAt = $now;
        $this->startedBy = $actor;
        $this->autoCloseAt = ActivityPolicy::autoCloseAt($now, $this->estimatedDurationMinutes);
        $this->touch();

        return Result::success(true);
    }

    /**
     * @return Result<bool> `true` quando a transição ocorreu
     */
    public function finish(ActivityUserId $actor, Instant $now): Result
    {
        if (ActivityStatus::FINISHED === $this->status) {
            return Result::success(false);
        }

        if (ActivityStatus::CANCELLED === $this->status) {
            return Result::failure([ActivityErrors::cancelled()]);
        }

        if (ActivityStatus::INVALIDATED === $this->status) {
            return Result::failure([ActivityErrors::invalidated()]);
        }

        if (ActivityStatus::STARTED !== $this->status || null === $this->startedAt) {
            return Result::failure([ActivityErrors::notStarted()]);
        }

        if ($now->isAfter(ActivityPolicy::finishClosesAt($this->startedAt))) {
            return Result::failure([ActivityErrors::finishWindowClosed()]);
        }

        $this->status = ActivityStatus::FINISHED;
        $this->finishedAt = $now;
        $this->finishedBy = $actor;
        $this->finishedAutomatically = false;
        $this->touch();

        return Result::success(true);
    }

    /**
     * Fecha automaticamente uma atividade iniciada, usando o horário lógico
     * calculado no início (autoCloseAt).
     */
    public function finishAutomatically(): bool
    {
        if (ActivityStatus::STARTED !== $this->status || null === $this->startedAt) {
            return false;
        }

        $this->status = ActivityStatus::FINISHED;
        $this->finishedAt = $this->autoCloseAt ?? ActivityPolicy::autoCloseAt(
            $this->startedAt,
            $this->estimatedDurationMinutes,
        );
        $this->finishedBy = null;
        $this->finishedAutomatically = true;
        $this->touch();

        return true;
    }

    /**
     * Cancela automaticamente uma atividade que não foi iniciada.
     */
    public function cancelAutomatically(): bool
    {
        if (ActivityStatus::SCHEDULED !== $this->status) {
            return false;
        }

        $this->status = ActivityStatus::CANCELLED;
        $this->cancelledAt = ActivityPolicy::autoCancelNotStartedAt($this->startsAt);
        $this->cancelledBy = null;
        $this->cancelledAutomatically = true;
        $this->cancellationReason = 'Cancelada automaticamente por não ter sido iniciada.';
        $this->touch();

        return true;
    }

    /**
     * @return Result<bool> `true` quando a transição ocorreu
     */
    public function invalidate(ActivityUserId $actor, ActivityReason $reason, Instant $now): Result
    {
        if (ActivityStatus::INVALIDATED === $this->status) {
            return Result::success(false);
        }

        if (ActivityStatus::CANCELLED === $this->status) {
            return Result::failure([ActivityErrors::cancelled()]);
        }

        if (ActivityStatus::SCHEDULED === $this->status) {
            return Result::failure([ActivityErrors::notStarted()]);
        }

        $reference = ActivityStatus::FINISHED === $this->status
            ? $this->finishedAt
            : ($this->autoCloseAt ?? $this->endsAt);

        if (null === $reference || $now->isAfter(ActivityPolicy::invalidationClosesAt($reference))) {
            return Result::failure([ActivityErrors::invalidationWindowClosed()]);
        }

        $this->status = ActivityStatus::INVALIDATED;
        $this->invalidatedAt = $now;
        $this->invalidatedBy = $actor;
        $this->invalidationReason = $reason->value();
        $this->touch();

        return Result::success(true);
    }

    /**
     * @return Result<Attendance>
     */
    public function enroll(ActivityUserId $userId, Instant $now): Result
    {
        if (!$this->isScheduled() || !$now->isBefore($this->startsAt)) {
            return Result::failure([ActivityErrors::enrollmentNotAllowed()]);
        }

        $existing = $this->attendanceFor($userId);

        if (null !== $existing) {
            return Result::success($existing);
        }

        $attendance = Attendance::enrolled($this, $userId);
        $this->attendances->add($attendance);
        $this->touch();

        return Result::success($attendance);
    }

    /**
     * @return Result<bool> `true` quando a inscrição foi cancelada
     */
    public function cancelEnrollment(ActivityUserId $userId, Instant $now): Result
    {
        $attendance = $this->attendanceFor($userId);

        if (null === $attendance) {
            return Result::success(false);
        }

        if (!$this->isScheduled() || !$now->isBefore($this->startsAt)) {
            return Result::failure([ActivityErrors::enrollmentNotAllowed()]);
        }

        if (AttendanceStatus::CHECKED_IN === $attendance->status()
            || AttendanceStatus::VALIDATED === $attendance->status()
        ) {
            return Result::failure([ActivityErrors::enrollmentNotActive()]);
        }

        $attendance->cancel($now);
        $this->touch();

        return Result::success(true);
    }

    /**
     * @return Result<Attendance>
     */
    public function checkIn(ActivityUserId $userId, Instant $now): Result
    {
        $attendance = $this->attendanceFor($userId);

        if (null === $attendance) {
            return Result::failure([ActivityErrors::enrollmentRequired()]);
        }

        if (AttendanceStatus::CHECKED_IN === $attendance->status()
            || AttendanceStatus::VALIDATED === $attendance->status()
        ) {
            return Result::success($attendance);
        }

        if (!$this->isScheduled() && !$this->isStarted()) {
            return Result::failure([ActivityErrors::checkInWindowClosed()]);
        }

        if ($now->isBefore(ActivityPolicy::checkInOpensAt($this->startsAt))
            || $now->isAfter(ActivityPolicy::checkInClosesAt($this->endsAt))
        ) {
            return Result::failure([ActivityErrors::checkInWindowClosed()]);
        }

        $attendance->checkIn($now);
        $this->touch();

        return Result::success($attendance);
    }

    /**
     * @return Result<Attendance>
     */
    public function validateAttendance(
        AttendanceId $attendanceId,
        ActivityUserId $actor,
        Instant $now,
    ): Result {
        $attendance = $this->attendanceById($attendanceId);

        if (null === $attendance) {
            return Result::failure([ActivityErrors::attendanceNotFound()]);
        }

        if (AttendanceStatus::VALIDATED === $attendance->status()) {
            return Result::success($attendance);
        }

        if (AttendanceStatus::CHECKED_IN !== $attendance->status()) {
            return Result::failure([ActivityErrors::attendanceNotCheckedIn()]);
        }

        if (ActivityStatus::CANCELLED === $this->status) {
            return Result::failure([ActivityErrors::cancelled()]);
        }

        if (ActivityStatus::INVALIDATED === $this->status) {
            return Result::failure([ActivityErrors::invalidated()]);
        }

        if (!$this->isFinished() || null === $this->finishedAt) {
            return Result::failure([ActivityErrors::notFinished()]);
        }

        if ($now->isBefore(ActivityPolicy::validationOpensAt($this->finishedAt, $this->endsAt))
            || $now->isAfter(ActivityPolicy::validationClosesAt($this->finishedAt))
        ) {
            return Result::failure([ActivityErrors::validationWindowClosed()]);
        }

        $attendance->validate($actor, $now);
        $this->touch();

        return Result::success($attendance);
    }

    public function id(): ActivityId
    {
        return $this->id;
    }

    public function groupId(): ActivityGroupId
    {
        return $this->groupId;
    }

    public function createdBy(): ActivityUserId
    {
        return $this->createdBy;
    }

    public function title(): ActivityTitle
    {
        return $this->title;
    }

    public function description(): ?ActivityDescription
    {
        return $this->description;
    }

    public function window(): ActivityWindow
    {
        return ActivityWindow::restore($this->startsAt, $this->endsAt, $this->estimatedDurationMinutes);
    }

    public function meetingPoint(): MeetingPoint
    {
        return $this->meetingPoint;
    }

    public function route(): ActivityRoute
    {
        return $this->route;
    }

    public function estimatedDistanceMeters(): int
    {
        return $this->estimatedDistanceMeters;
    }

    public function status(): ActivityStatus
    {
        return $this->status;
    }

    public function startedAt(): ?Instant
    {
        return $this->startedAt;
    }

    public function startedBy(): ?ActivityUserId
    {
        return $this->startedBy;
    }

    public function autoCloseAt(): ?Instant
    {
        return $this->autoCloseAt;
    }

    public function finishedAt(): ?Instant
    {
        return $this->finishedAt;
    }

    public function finishedBy(): ?ActivityUserId
    {
        return $this->finishedBy;
    }

    public function finishedAutomatically(): bool
    {
        return $this->finishedAutomatically;
    }

    public function cancelledAt(): ?Instant
    {
        return $this->cancelledAt;
    }

    public function cancelledBy(): ?ActivityUserId
    {
        return $this->cancelledBy;
    }

    public function cancelledAutomatically(): bool
    {
        return $this->cancelledAutomatically;
    }

    public function cancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function invalidatedAt(): ?Instant
    {
        return $this->invalidatedAt;
    }

    public function invalidatedBy(): ?ActivityUserId
    {
        return $this->invalidatedBy;
    }

    public function invalidationReason(): ?string
    {
        return $this->invalidationReason;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    public function isScheduled(): bool
    {
        return ActivityStatus::SCHEDULED === $this->status;
    }

    public function isStarted(): bool
    {
        return ActivityStatus::STARTED === $this->status;
    }

    public function isFinished(): bool
    {
        return ActivityStatus::FINISHED === $this->status;
    }

    public function isCancelled(): bool
    {
        return ActivityStatus::CANCELLED === $this->status;
    }

    public function isInvalidated(): bool
    {
        return ActivityStatus::INVALIDATED === $this->status;
    }

    public function isXpEligible(): bool
    {
        return $this->isFinished() && $this->checkedInCount() >= ActivityPolicy::MIN_CHECKED_IN_FOR_XP;
    }

    /**
     * Fim efetivo para conflito: uma atividade iniciada usa o autoCloseAt,
     * que inclui a tolerância.
     */
    public function effectiveEndsAt(): Instant
    {
        return $this->autoCloseAt ?? $this->endsAt;
    }

    /**
     * @return list<Attendance>
     */
    public function attendances(): array
    {
        $items = array_values($this->attendances->toArray());

        usort(
            $items,
            static fn (Attendance $a, Attendance $b): int => $a->createdAt()->format('U.u') <=> $b->createdAt()->format('U.u'),
        );

        return $items;
    }

    public function attendanceFor(ActivityUserId $userId): ?Attendance
    {
        foreach ($this->attendances as $attendance) {
            if ($attendance->userId()->equals($userId) && $attendance->status()->isActive()) {
                return $attendance;
            }
        }

        return null;
    }

    public function attendanceById(AttendanceId $attendanceId): ?Attendance
    {
        foreach ($this->attendances as $attendance) {
            if ($attendance->id()->equals($attendanceId)) {
                return $attendance;
            }
        }

        return null;
    }

    public function enrolledCount(): int
    {
        return count(array_filter(
            $this->attendances->toArray(),
            static fn (Attendance $attendance): bool => $attendance->status()->isActive(),
        ));
    }

    public function checkedInCount(): int
    {
        return count(array_filter(
            $this->attendances->toArray(),
            static fn (Attendance $attendance): bool => AttendanceStatus::CHECKED_IN === $attendance->status()
                || AttendanceStatus::VALIDATED === $attendance->status(),
        ));
    }

    public function validatedCount(): int
    {
        return count(array_filter(
            $this->attendances->toArray(),
            static fn (Attendance $attendance): bool => AttendanceStatus::VALIDATED === $attendance->status(),
        ));
    }

    /**
     * @return list<ActivityReschedule>
     */
    public function reschedules(): array
    {
        $items = array_values($this->reschedules->toArray());

        usort(
            $items,
            static fn (ActivityReschedule $a, ActivityReschedule $b): int => $a->changedAt()->format('U.u') <=> $b->changedAt()->format('U.u'),
        );

        return $items;
    }

    private function notScheduledError(): \App\Shared\Domain\Error\DomainError
    {
        return match (true) {
            $this->isCancelled() => ActivityErrors::cancelled(),
            $this->isInvalidated() => ActivityErrors::invalidated(),
            default => ActivityErrors::notScheduled(),
        };
    }

    private function touch(): void
    {
        $this->updatedAt = Instant::now();
    }
}
