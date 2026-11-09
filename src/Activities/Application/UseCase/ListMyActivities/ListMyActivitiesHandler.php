<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\ListMyActivities;

use App\Activities\Domain\Activity\Repository\ActivityPage;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;

final readonly class ListMyActivitiesHandler
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private GroupAccessReaderInterface $groupAccessReader,
    ) {
    }

    /**
     * @return Result<ActivityPage>
     */
    public function __invoke(ListMyActivitiesQuery $query): Result
    {
        $approvedGroupIds = $this->groupAccessReader->approvedGroupIds($query->userId->value());

        if ([] === $approvedGroupIds) {
            return Result::success(new ActivityPage(items: [], total: 0));
        }

        return Result::success($this->activityRepository->searchByParticipant(
            $query->userId,
            $query->criteria->withGroupIds($approvedGroupIds),
        ));
    }
}
