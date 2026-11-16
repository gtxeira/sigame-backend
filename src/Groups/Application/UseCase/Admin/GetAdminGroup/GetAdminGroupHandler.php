<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\Admin\GetAdminGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Obtém um grupo para a administração SIGAME, inclusive arquivado ou excluído.
 *
 * A leitura não exige vínculo: é o ponto de partida da moderação global.
 */
final readonly class GetAdminGroupHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    /**
     * @return Result<Group>
     */
    public function __invoke(GetAdminGroupQuery $query): Result
    {
        $group = $this->groupRepository->findById($query->groupId);

        if (null === $group) {
            return Result::failure([GroupErrors::groupNotFound()]);
        }

        return Result::success($group);
    }
}
