<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\DesignateSupervisor;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Event\SupervisorDesignated;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Designa um membro aprovado como supervisor do grupo.
 */
final readonly class DesignateSupervisorHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Membership>
     */
    public function __invoke(DesignateSupervisorCommand $command): Result
    {
        return $this->groupRepository->transactional(function () use ($command): Result {
            $group = $this->groupRepository->findByIdForUpdate($command->groupId);

            if (null === $group || $group->isDeleted()) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            $designation = $group->designateSupervisor($command->membershipId, $command->actorId);

            if ($designation->isFailure()) {
                return Result::failure($designation->errors());
            }

            /** @var Membership $membership */
            $membership = $designation->value();

            $this->groupRepository->save($group);

            $this->eventDispatcher->dispatch(SupervisorDesignated::now(
                groupId: $group->id()->value(),
                membershipId: $membership->id()->value(),
                userId: $membership->userId()->value(),
                actorId: $command->actorId->value(),
            ));

            return Result::success($membership);
        });
    }
}
