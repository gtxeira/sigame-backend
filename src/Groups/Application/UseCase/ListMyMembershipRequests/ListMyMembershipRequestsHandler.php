<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ListMyMembershipRequests;

use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Lista as solicitações e participações do próprio usuário.
 */
final readonly class ListMyMembershipRequestsHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * @return Result<list<Membership>>
     */
    public function __invoke(ListMyMembershipRequestsQuery $query): Result
    {
        return Result::success(
            $this->groupRepository->findMembershipsOfUser($query->viewerId),
        );
    }
}
