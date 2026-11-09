<?php

declare(strict_types=1);

namespace App\Activities\Presentation\Http\Response;

use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\ValueObject\ActivityStatus;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Resumo de uma atividade.')]
final readonly class ActivitySummaryResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $id,
        #[OA\Property(format: 'uuid')]
        public string $groupId,
        #[OA\Property(example: 'Corrida no Parque')]
        public string $title,
        #[OA\Property(format: 'date-time')]
        public string $startsAt,
        #[OA\Property(format: 'date-time')]
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
        #[OA\Property(example: 2)]
        public int $enrolledCount,
        #[OA\Property(ref: ViewerAttendanceResponse::class, nullable: true)]
        public ?ViewerAttendanceResponse $viewerAttendance,
        #[OA\Property(example: true)]
        public bool $canManage,
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
            title: $activity->title()->value(),
            startsAt: $activity->window()->startsAt()->toIsoStringWithMilliseconds(),
            endsAt: $activity->window()->endsAt()->toIsoStringWithMilliseconds(),
            estimatedDurationMinutes: $activity->window()->estimatedDurationMinutes(),
            meetingPoint: $activity->meetingPoint()->value(),
            route: $activity->route()->value(),
            estimatedDistanceMeters: $activity->estimatedDistanceMeters(),
            status: $activity->status()->value,
            enrolledCount: $activity->enrolledCount(),
            viewerAttendance: null === $attendance ? null : ViewerAttendanceResponse::fromEntity($attendance),
            canManage: $canManage,
        );
    }
}
