<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\DeleteGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Event\GroupDeleted;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Application\Administration\AdminAuditRecord;
use App\Shared\Application\Administration\AdminAuditRecorderInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\AdminAuditAction;
use App\Shared\Domain\ValueObject\AdminAuditTargetType;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Exclui logicamente o grupo. A operação é terminal e preserva o histórico.
 *
 * Sem exclusão física: atividades, presenças validadas e episódios de
 * participação continuam existindo para auditoria e gamificação.
 */
final readonly class DeleteGroupHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
        private AdminAuditRecorderInterface $auditRecorder,
    ) {
    }

    /**
     * @return Result<bool>
     */
    public function __invoke(DeleteGroupCommand $command): Result
    {
        return $this->groupRepository->transactional(function () use ($command): Result {
            $group = $this->groupRepository->findByIdForUpdate($command->groupId);

            if (null === $group || $group->isDeleted()) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            $delete = $group->delete(
                actor: $command->actorId,
                reason: $command->reason,
                at: Instant::now(),
                isAdminSigame: $command->isAdminSigame,
            );

            if ($delete->isFailure()) {
                return Result::failure($delete->errors());
            }

            $this->groupRepository->save($group);

            if ($command->isAdminSigame) {
                $this->auditRecorder->record(new AdminAuditRecord(
                    action: AdminAuditAction::GroupDeleted,
                    targetType: AdminAuditTargetType::Group,
                    targetId: $group->id()->value(),
                    targetLabel: $group->name()->value(),
                    actorId: $command->actorId->value(),
                    reason: $group->deleteReason(),
                ));
            }

            $this->eventDispatcher->dispatch(GroupDeleted::now(
                groupId: $group->id()->value(),
                ownerId: $group->ownerId()->value(),
                deletedBy: $group->deletedBy()?->value() ?? $command->actorId->value(),
                reason: $group->deleteReason(),
            ));

            return Result::success(true);
        });
    }
}
