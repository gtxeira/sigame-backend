<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\UnarchiveGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\GroupUnarchived;
use App\Groups\Domain\Group\Exception\DuplicateGroupException;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Application\Administration\AdminAuditRecord;
use App\Shared\Application\Administration\AdminAuditRecorderInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\AdminAuditAction;
use App\Shared\Domain\ValueObject\AdminAuditTargetType;

/**
 * Reativa um grupo arquivado.
 */
final readonly class UnarchiveGroupHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
        private AdminAuditRecorderInterface $auditRecorder,
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

            if ($group->isArchived() && $this->groupRepository->existsByOwnerAndName(
                owner: $group->ownerId(),
                name: $group->name(),
                modality: $group->modality(),
                excludeGroupId: $group->id(),
            )) {
                return Result::failure([GroupErrors::duplicateGroup()]);
            }

            $unarchive = $group->unarchive($command->actorId, $command->isAdminSigame);

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

            if ($command->isAdminSigame) {
                $this->auditRecorder->record(new AdminAuditRecord(
                    action: AdminAuditAction::GroupUnarchived,
                    targetType: AdminAuditTargetType::Group,
                    targetId: $group->id()->value(),
                    targetLabel: $group->name()->value(),
                    actorId: $command->actorId->value(),
                ));
            }

            $this->eventDispatcher->dispatch(GroupUnarchived::now(
                groupId: $group->id()->value(),
                actorId: $command->actorId->value(),
            ));

            return Result::success($group);
        });
    }
}
