<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Activity;

use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Activities\Domain\Activity\ValueObject\AttendanceStatus;
use App\Shared\Application\Activity\ActivitySummary;
use App\Shared\Application\Activity\ActivitySummaryReaderInterface;
use App\Shared\Domain\Exception\InvalidUuidException;

/**
 * Adapter Doctrine do resumo de atividade para notificações.
 */
final readonly class DoctrineActivitySummaryReader implements ActivitySummaryReaderInterface
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
    ) {
    }

    public function summary(string $activityId): ?ActivitySummary
    {
        try {
            $id = ActivityId::fromString($activityId);
        } catch (InvalidUuidException) {
            return null;
        }

        $activity = $this->activityRepository->findById($id);

        if (null === $activity) {
            return null;
        }

        $enrolled = [];
        $checkedIn = [];

        foreach ($activity->attendances() as $attendance) {
            $status = $attendance->status();

            if (!$status->isActive()) {
                continue;
            }

            $userId = $attendance->userId()->value();
            $enrolled[] = $userId;

            if (AttendanceStatus::ENROLLED !== $status) {
                $checkedIn[] = $userId;
            }
        }

        return new ActivitySummary(
            id: $activity->id()->value(),
            groupId: $activity->groupId()->value(),
            title: $activity->title()->value(),
            startsAt: $activity->window()->startsAt(),
            endsAt: $activity->window()->endsAt(),
            meetingPoint: $activity->meetingPoint()->value(),
            enrolledUserIds: array_values(array_unique($enrolled)),
            checkedInUserIds: array_values(array_unique($checkedIn)),
        );
    }
}
