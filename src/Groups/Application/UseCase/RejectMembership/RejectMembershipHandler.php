<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\RejectMembership;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Event\MembershipRejected;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Rejeita uma solicitação de entrada pendente.
 */
final readonly class RejectMembershipHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Membership>
     */
    public function __invoke(RejectMembershipCommand $command): Result
    {
        return $this->groupRepository->transactional(function () use ($command): Result {
            $group = $this->groupRepository->findByIdForUpdate($command->groupId);

            if (null === $group || $group->isDeleted()) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            $rejection = $group->rejectMembership($command->membershipId, $command->actorId);

            if ($rejection->isFailure()) {
                return Result::failure($rejection->errors());
            }

            /** @var Membership $membership */
            $membership = $rejection->value();

            $this->groupRepository->save($group);

            $this->eventDispatcher->dispatch(MembershipRejected::now(
                groupId: $group->id()->value(),
                membershipId: $membership->id()->value(),
                userId: $membership->userId()->value(),
                decidedBy: $command->actorId->value(),
            ));

            return Result::success($membership);
        });
    }
}
