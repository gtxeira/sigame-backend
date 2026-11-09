<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ClearSupervisor;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Event\SupervisorRemoved;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Remove a designação de supervisor, de forma idempotente.
 */
final readonly class ClearSupervisorHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<bool>
     */
    public function __invoke(ClearSupervisorCommand $command): Result
    {
        return $this->groupRepository->transactional(function () use ($command): Result {
            $group = $this->groupRepository->findByIdForUpdate($command->groupId);

            if (null === $group) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            $clear = $group->clearSupervisor($command->actorId);

            if ($clear->isFailure()) {
                return Result::failure($clear->errors());
            }

            /** @var bool $changed */
            $changed = $clear->value();

            if ($changed) {
                $this->groupRepository->save($group);

                $this->eventDispatcher->dispatch(SupervisorRemoved::now(
                    groupId: $group->id()->value(),
                    actorId: $command->actorId->value(),
                ));
            }

            return Result::success($changed);
        });
    }
}
