<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ArchiveGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\GroupArchived;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Application\Administration\AdminAuditRecord;
use App\Shared\Application\Administration\AdminAuditRecorderInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\AdminAuditAction;
use App\Shared\Domain\ValueObject\AdminAuditTargetType;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Arquiva o grupo, deixando-o em somente leitura e fora da busca.
 */
final readonly class ArchiveGroupHandler
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
    public function __invoke(ArchiveGroupCommand $command): Result
    {
        return $this->groupRepository->transactional(function () use ($command): Result {
            $group = $this->groupRepository->findByIdForUpdate($command->groupId);

            if (null === $group || $group->isDeleted()) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            $archive = $group->archive(
                actor: $command->actorId,
                reason: $command->reason,
                automatic: false,
                at: Instant::now(),
                isAdminSigame: $command->isAdminSigame,
            );

            if ($archive->isFailure()) {
                return Result::failure($archive->errors());
            }

            if (false === $archive->value()) {
                return Result::success($group);
            }

            $this->groupRepository->save($group);

            if ($command->isAdminSigame) {
                $this->auditRecorder->record(new AdminAuditRecord(
                    action: AdminAuditAction::GroupArchived,
                    targetType: AdminAuditTargetType::Group,
                    targetId: $group->id()->value(),
                    targetLabel: $group->name()->value(),
                    actorId: $command->actorId->value(),
                    reason: $group->archiveReason(),
                ));
            }

            $this->eventDispatcher->dispatch(GroupArchived::now(
                groupId: $group->id()->value(),
                ownerId: $group->ownerId()->value(),
                archivedBy: $group->archivedBy()?->value(),
                reason: $group->archiveReason(),
                automatic: false,
            ));

            return Result::success($group);
        });
    }
}
