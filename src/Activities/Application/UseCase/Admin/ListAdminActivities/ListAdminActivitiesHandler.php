<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\Admin\ListAdminActivities;

use App\Activities\Domain\Activity\Repository\ActivityPage;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Lista atividades de todos os grupos para a administração SIGAME.
 */
final readonly class ListAdminActivitiesHandler
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
    ) {
    }

    /**
     * @return Result<ActivityPage>
     */
    public function __invoke(ListAdminActivitiesQuery $query): Result
    {
        return Result::success($this->activityRepository->searchAll($query->criteria));
    }
}
