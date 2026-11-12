<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ListGroups;

use App\Groups\Domain\Group\Repository\GroupPage;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\Repository\GroupSearchCriteria;
use App\Shared\Application\Activity\GroupActivityReaderInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Lista grupos com filtros e paginação.
 *
 * Quando a Home pede um período, os grupos com atividade na janela são
 * resolvidos pela porta compartilhada antes da consulta (o módulo Groups não
 * importa Activities).
 */
final readonly class ListGroupsHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private GroupActivityReaderInterface $groupActivityReader,
    ) {
    }

    /**
     * @return Result<GroupPage>
     */
    public function __invoke(ListGroupsQuery $query): Result
    {
        $criteria = $this->withActivityFilter($query->criteria);

        return Result::success($this->groupRepository->search($criteria));
    }

    private function withActivityFilter(GroupSearchCriteria $criteria): GroupSearchCriteria
    {
        if (null === $criteria->activityFrom || null === $criteria->activityTo) {
            return $criteria;
        }

        return $criteria->withActivityGroupIds(
            $this->groupActivityReader->groupIdsWithActivityInRange(
                $criteria->activityFrom,
                $criteria->activityTo,
            ),
        );
    }
}
