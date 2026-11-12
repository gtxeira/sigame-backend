<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\DeleteGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Event\GroupDeleted;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
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
            );

            if ($delete->isFailure()) {
                return Result::failure($delete->errors());
            }

            $this->groupRepository->save($group);

            $this->eventDispatcher->dispatch(GroupDeleted::now(
                groupId: $group->id()->value(),
                createdBy: $group->createdBy()->value(),
                deletedBy: $group->deletedBy()?->value() ?? $command->actorId->value(),
                reason: $group->deleteReason(),
            ));

            return Result::success(true);
        });
    }
}
