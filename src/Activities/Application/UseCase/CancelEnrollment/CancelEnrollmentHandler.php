<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\CancelEnrollment;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Domain\Activity\Event\ActivityEnrollmentCancelled;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;

final readonly class CancelEnrollmentHandler
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private GroupAccessReaderInterface $groupAccessReader,
        private ActivityAccessPolicy $accessPolicy,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<bool>
     */
    public function __invoke(CancelEnrollmentCommand $command): Result
    {
        return $this->activityRepository->transactional(function () use ($command): Result {
            $activity = $this->activityRepository->findByIdForUpdate($command->activityId);

            if (null === $activity) {
                return Result::failure([ActivityErrors::activityNotFound()]);
            }

            $group = $this->groupAccessReader->snapshot(
                $activity->groupId()->value(),
                $command->userId->value(),
            );
            $accessError = $this->accessPolicy->canParticipate($group);

            if (null !== $accessError) {
                return Result::failure([$accessError]);
            }

            $this->activityRepository->lockUser($command->userId);

            $changed = $activity->cancelEnrollment($command->userId, Instant::now());

            if ($changed->isFailure()) {
                return Result::failure($changed->errors());
            }

            if (true === $changed->value()) {
                $this->activityRepository->save($activity);
                $this->eventDispatcher->dispatch(ActivityEnrollmentCancelled::now(
                    activityId: $activity->id()->value(),
                    groupId: $activity->groupId()->value(),
                    userId: $command->userId->value(),
                ));
            }

            return $changed;
        });
    }
}
