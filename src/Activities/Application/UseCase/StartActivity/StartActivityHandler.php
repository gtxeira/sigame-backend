<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\StartActivity;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Event\ActivityStarted;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;

final readonly class StartActivityHandler
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
    public function __invoke(StartActivityCommand $command): Result
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
            $accessError = $this->accessPolicy->canOperate($group, $command->isAdminSigame);

            if (null !== $accessError) {
                return Result::failure([$accessError]);
            }

            $changed = $activity->start($command->actorId, Instant::now());

            if ($changed->isFailure()) {
                return Result::failure($changed->errors());
            }

            if (true === $changed->value()) {
                $this->activityRepository->save($activity);
                $this->eventDispatcher->dispatch(ActivityStarted::now(
                    activityId: $activity->id()->value(),
                    groupId: $activity->groupId()->value(),
                    actorId: $command->actorId->value(),
                ));
            }

            return Result::success($activity);
        });
    }
}
