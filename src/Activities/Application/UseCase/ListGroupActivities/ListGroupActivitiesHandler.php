<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\ListGroupActivities;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Domain\Activity\Repository\ActivityPage;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;

final readonly class ListGroupActivitiesHandler
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private GroupAccessReaderInterface $groupAccessReader,
        private ActivityAccessPolicy $accessPolicy,
    ) {
    }

    /**
     * @return Result<ActivityPage>
     */
    public function __invoke(ListGroupActivitiesQuery $query): Result
    {
        $group = $this->groupAccessReader->snapshot(
            $query->groupId->value(),
            $query->viewerId->value(),
        );

        $error = $this->accessPolicy->canView($group, $query->isAdminSigame);

        if (null !== $error) {
            return Result::failure([$error]);
        }

        return Result::success($this->activityRepository->searchByGroup(
            $query->groupId,
            $query->criteria,
        ));
    }
}
