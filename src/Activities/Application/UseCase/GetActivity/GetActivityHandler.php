<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\GetActivity;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;

final readonly class GetActivityHandler
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private GroupAccessReaderInterface $groupAccessReader,
        private ActivityAccessPolicy $accessPolicy,
    ) {
    }

    /**
     * @return Result<Activity>
     */
    public function __invoke(GetActivityQuery $query): Result
    {
        $activity = $this->activityRepository->findById($query->activityId);

        if (null === $activity) {
            return Result::failure([ActivityErrors::activityNotFound()]);
        }

        $group = $this->groupAccessReader->snapshot(
            $activity->groupId()->value(),
            $query->viewerId->value(),
        );

        $error = $this->accessPolicy->canView($group, $query->isAdminSigame);

        if (null !== $error) {
            return Result::failure([$error]);
        }

        return Result::success($activity);
    }
}
