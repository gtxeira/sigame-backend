<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\GetGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Obtém um grupo para um usuário autenticado.
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
        $group = $this->groupRepository->findById($query->groupId);

        if (null === $group) {
            return Result::failure([GroupErrors::groupNotFound()]);
        }

        return Result::success($group);
    }
}
