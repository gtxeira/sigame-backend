<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\UnarchiveGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\GroupUnarchived;
use App\Groups\Domain\Group\Exception\DuplicateGroupException;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Reativa um grupo arquivado.
 */
final readonly class UnarchiveGroupHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Group>
     */
    public function __invoke(UnarchiveGroupCommand $command): Result
    {
        return $this->groupRepository->transactional(function () use ($command): Result {
            $group = $this->groupRepository->findByIdForUpdate($command->groupId);

            if (null === $group || $group->isDeleted()) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            if ($group->isArchived() && $this->groupRepository->existsByCreatorAndName(
                creator: $group->createdBy(),
                name: $group->name(),
                modality: $group->modality(),
                excludeGroupId: $group->id(),
            )) {
                return Result::failure([GroupErrors::duplicateGroup()]);
            }

            $unarchive = $group->unarchive($command->actorId);

            if ($unarchive->isFailure()) {
                return Result::failure($unarchive->errors());
            }

            if (false === $unarchive->value()) {
                return Result::success($group);
            }

            try {
                $this->groupRepository->save($group);
            } catch (DuplicateGroupException) {
                return Result::failure([GroupErrors::duplicateGroup()]);
            }

            $this->eventDispatcher->dispatch(GroupUnarchived::now(
                groupId: $group->id()->value(),
                actorId: $command->actorId->value(),
            ));

            return Result::success($group);
        });
    }
}
