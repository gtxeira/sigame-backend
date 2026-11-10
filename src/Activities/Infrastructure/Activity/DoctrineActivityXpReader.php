<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Activity;

use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\ValueObject\ActivityId;
use App\Shared\Application\Activity\ActivityXpContext;
use App\Shared\Application\Activity\ActivityXpReaderInterface;
use App\Shared\Application\Group\GroupModalityReaderInterface;
use App\Shared\Domain\Exception\InvalidUuidException;

/**
 * Adapter Doctrine do contexto de XP de uma atividade.
 */
final readonly class DoctrineActivityXpReader implements ActivityXpReaderInterface
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private GroupModalityReaderInterface $groupModalityReader,
    ) {
    }

    public function contextOf(string $activityId): ?ActivityXpContext
    {
        try {
            $activity = $this->activityRepository->findById(ActivityId::fromString($activityId));
        } catch (InvalidUuidException) {
            return null;
        }

        if (null === $activity) {
            return null;
        }

        $groupId = $activity->groupId()->value();
        $modality = $this->groupModalityReader->modalityOf($groupId);

        if (null === $modality) {
            return null;
        }

        return new ActivityXpContext(
            activityId: $activity->id()->value(),
            groupId: $groupId,
            modality: $modality,
            distanceMeters: $activity->estimatedDistanceMeters(),
            durationMinutes: $activity->window()->estimatedDurationMinutes(),
            startsAt: $activity->window()->startsAt(),
            checkedInParticipants: $activity->checkedInCount(),
        );
    }
}
