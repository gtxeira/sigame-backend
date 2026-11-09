<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Response;

use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\ValueObject\ActivityStatus;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Dados de uma atividade.')]
final readonly class ActivityResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $id,
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $groupId,
        #[OA\Property(format: 'uuid', example: '01a0d4ac-5d8d-7712-a6e3-f44a6cabc55f')]
        public string $createdBy,
        #[OA\Property(example: 'Corrida no Parque')]
        public string $title,
        #[OA\Property(nullable: true)]
        public ?string $description,
        #[OA\Property(format: 'date-time', example: '2026-11-15T10:00:00.000+00:00')]
        public string $startsAt,
        #[OA\Property(format: 'date-time', example: '2026-11-15T11:00:00.000+00:00')]
        public string $endsAt,
        #[OA\Property(nullable: true, example: 60)]
        public ?int $estimatedDurationMinutes,
        #[OA\Property(example: 'Portão principal do parque')]
        public string $meetingPoint,
        #[OA\Property(example: 'Volta completa na pista externa')]
        public string $route,
        #[OA\Property(example: 5000)]
        public int $estimatedDistanceMeters,
        #[OA\Property(example: ActivityStatus::SCHEDULED, enum: ActivityStatus::class)]
        public string $status,
        #[OA\Property(format: 'date-time', nullable: true)]
        public ?string $startedAt,
        #[OA\Property(format: 'uuid', nullable: true)]
        public ?string $startedBy,
        #[OA\Property(format: 'date-time', nullable: true)]
        public ?string $autoCloseAt,
        #[OA\Property(format: 'date-time', nullable: true)]
        public ?string $finishedAt,
        #[OA\Property(format: 'uuid', nullable: true)]
        public ?string $finishedBy,
        #[OA\Property(example: false)]
        public bool $finishedAutomatically,
        #[OA\Property(format: 'date-time', nullable: true)]
        public ?string $cancelledAt,
        #[OA\Property(format: 'uuid', nullable: true)]
        public ?string $cancelledBy,
        #[OA\Property(example: false)]
        public bool $cancelledAutomatically,
        #[OA\Property(nullable: true)]
        public ?string $cancellationReason,
        #[OA\Property(format: 'date-time', nullable: true)]
        public ?string $invalidatedAt,
        #[OA\Property(format: 'uuid', nullable: true)]
        public ?string $invalidatedBy,
        #[OA\Property(nullable: true)]
        public ?string $invalidationReason,
        #[OA\Property(example: 2)]
        public int $enrolledCount,
        #[OA\Property(example: 2)]
        public int $checkedInCount,
        #[OA\Property(example: 2)]
        public int $validatedCount,
        #[OA\Property(example: true)]
        public bool $xpEligible,
        #[OA\Property(ref: ViewerAttendanceResponse::class, nullable: true)]
        public ?ViewerAttendanceResponse $viewerAttendance,
        #[OA\Property(example: true)]
        public bool $canManage,
        #[OA\Property(format: 'date-time')]
        public string $createdAt,
        #[OA\Property(format: 'date-time')]
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(
        Activity $activity,
        ?ActivityUserId $viewer,
        bool $canManage,
    ): self {
        $attendance = null === $viewer ? null : $activity->attendanceFor($viewer);

        return new self(
            id: $activity->id()->value(),
            groupId: $activity->groupId()->value(),
            createdBy: $activity->createdBy()->value(),
            title: $activity->title()->value(),
            description: $activity->description()?->value(),
            startsAt: $activity->window()->startsAt()->toIsoStringWithMilliseconds(),
            endsAt: $activity->window()->endsAt()->toIsoStringWithMilliseconds(),
            estimatedDurationMinutes: $activity->window()->estimatedDurationMinutes(),
            meetingPoint: $activity->meetingPoint()->value(),
            route: $activity->route()->value(),
            estimatedDistanceMeters: $activity->estimatedDistanceMeters(),
            status: $activity->status()->value,
            startedAt: $activity->startedAt()?->toIsoStringWithMilliseconds(),
            startedBy: $activity->startedBy()?->value(),
            autoCloseAt: $activity->autoCloseAt()?->toIsoStringWithMilliseconds(),
            finishedAt: $activity->finishedAt()?->toIsoStringWithMilliseconds(),
            finishedBy: $activity->finishedBy()?->value(),
            finishedAutomatically: $activity->finishedAutomatically(),
            cancelledAt: $activity->cancelledAt()?->toIsoStringWithMilliseconds(),
            cancelledBy: $activity->cancelledBy()?->value(),
            cancelledAutomatically: $activity->cancelledAutomatically(),
            cancellationReason: $activity->cancellationReason(),
            invalidatedAt: $activity->invalidatedAt()?->toIsoStringWithMilliseconds(),
            invalidatedBy: $activity->invalidatedBy()?->value(),
            invalidationReason: $activity->invalidationReason(),
            enrolledCount: $activity->enrolledCount(),
            checkedInCount: $activity->checkedInCount(),
            validatedCount: $activity->validatedCount(),
            xpEligible: $activity->isXpEligible(),
            viewerAttendance: null === $attendance ? null : ViewerAttendanceResponse::fromEntity($attendance),
            canManage: $canManage,
            createdAt: $activity->createdAt()->toIsoStringWithMilliseconds(),
            updatedAt: $activity->updatedAt()->toIsoStringWithMilliseconds(),
        );
    }
}
