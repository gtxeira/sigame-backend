<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\GetGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Obtém um grupo para um usuário autenticado.
 *
 * Grupos arquivados só são visíveis para membros aprovados e para o criador.
 */
final readonly class GetGroupHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * @return Result<Group>
     */
    public function __invoke(GetGroupQuery $query): Result
    {
        $group = $this->groupRepository->findByIdExcludingDeleted($query->groupId);

        if (null === $group) {
            return Result::failure([GroupErrors::groupNotFound()]);
        }

        if ($group->isArchived() && !$this->canViewArchived($group, $query)) {
            return Result::failure([GroupErrors::groupNotFound()]);
        }

        return Result::success($group);
    }

    private function canViewArchived(Group $group, GetGroupQuery $query): bool
    {
        return $group->hasApprovedMembership($query->viewerId);
    }
}
