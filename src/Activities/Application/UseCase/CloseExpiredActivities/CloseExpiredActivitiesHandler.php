<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\CloseExpiredActivities;

use App\Activities\Domain\Activity\Event\ActivityCancelled;
use App\Activities\Domain\Activity\Event\ActivityFinished;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Fecha atividades iniciadas que excederam o prazo e cancela as que nunca
 * foram iniciadas. A operação é idempotente.
 */
final readonly class CloseExpiredActivitiesHandler
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(CloseExpiredActivitiesMessage $message): int
    {
        $now = Instant::now();
        $closed = 0;

        $candidates = [
            ...$this->activityRepository->findScheduledToAutoCancel($now),
            ...$this->activityRepository->findStartedToAutoFinish($now),
        ];

        foreach ($candidates as $activityId) {
            $changed = $this->activityRepository->transactional(function () use ($activityId): bool {
                $activity = $this->activityRepository->findByIdForUpdate($activityId);

                if (null === $activity) {
                    return false;
                }

                if ($activity->isScheduled() && $activity->cancelAutomatically()) {
                    $this->activityRepository->save($activity);
                    $this->eventDispatcher->dispatch(ActivityCancelled::now(
                        activityId: $activity->id()->value(),
                        groupId: $activity->groupId()->value(),
                        actorId: null,
                        reason: $activity->cancellationReason(),
                        automatic: true,
                    ));

                    return true;
                }

                if ($activity->isStarted() && $activity->finishAutomatically()) {
                    $this->activityRepository->save($activity);
                    $this->eventDispatcher->dispatch(ActivityFinished::now(
                        activityId: $activity->id()->value(),
                        groupId: $activity->groupId()->value(),
                        actorId: null,
                        automatic: true,
                    ));

                    return true;
                }

                return false;
            });

            if ($changed) {
                ++$closed;
            }
        }

        return $closed;
    }
}
