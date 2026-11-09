<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\ListReschedules;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Domain\Activity\Entity\ActivityReschedule;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;

final readonly class ListReschedulesHandler
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private GroupAccessReaderInterface $groupAccessReader,
        private ActivityAccessPolicy $accessPolicy,
    ) {
    }

    /**
     * @return Result<list<ActivityReschedule>>
     */
    public function __invoke(ListReschedulesQuery $query): Result
    {
        $activity = $this->activityRepository->findById($query->activityId);

        if (null === $activity) {
            return Result::failure([ActivityErrors::activityNotFound()]);
        }

        $group = $this->groupAccessReader->snapshot(
            $activity->groupId()->value(),
            $query->viewerId->value(),
        );
        $accessError = $this->accessPolicy->canManage($group, $query->isAdminSigame);

        if (null !== $accessError) {
            return Result::failure([$accessError]);
        }

        return Result::success($activity->reschedules());
    }
}
