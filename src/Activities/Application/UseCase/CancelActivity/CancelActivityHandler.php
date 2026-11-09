<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\CancelActivity;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Event\ActivityCancelled;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\ValueObject\ActivityReason;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;

final readonly class CancelActivityHandler
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private GroupAccessReaderInterface $groupAccessReader,
        private ActivityAccessPolicy $accessPolicy,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Activity>
     */
    public function __invoke(CancelActivityCommand $command): Result
    {
        return $this->activityRepository->transactional(function () use ($command): Result {
            $activity = $this->activityRepository->findByIdForUpdate($command->activityId);

            if (null === $activity) {
                return Result::failure([ActivityErrors::activityNotFound()]);
            }

            $group = $this->groupAccessReader->snapshot(
                $activity->groupId()->value(),
                $command->actorId->value(),
            );
            $accessError = $this->accessPolicy->canManage($group, $command->isAdminSigame);

            if (null !== $accessError) {
                return Result::failure([$accessError]);
            }

            $reason = null;

            if (null !== $command->reason && '' !== trim($command->reason)) {
                $result = ActivityReason::fromString($command->reason);

                if ($result->isInvalid()) {
                    return Result::failure($result->errors());
                }

                $reason = $result->value();
            }

            $changed = $activity->cancel(
                actor: $command->actorId,
                reason: $reason,
                now: Instant::now(),
            );

            if ($changed->isFailure()) {
                return Result::failure($changed->errors());
            }

            if (true === $changed->value()) {
                $this->activityRepository->save($activity);
                $this->eventDispatcher->dispatch(ActivityCancelled::now(
                    activityId: $activity->id()->value(),
                    groupId: $activity->groupId()->value(),
                    actorId: $command->actorId->value(),
                    reason: $activity->cancellationReason(),
                ));
            }

            return Result::success($activity);
        });
    }
}
