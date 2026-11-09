<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ApproveMembership;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Event\GroupActivated;
use App\Groups\Domain\Group\Event\MembershipApproved;
use App\Groups\Domain\Group\Exception\MembershipAlreadyExistsException;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Aprova uma solicitação de entrada.
 */
final readonly class ApproveMembershipHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Membership>
     */
    public function __invoke(ApproveMembershipCommand $command): Result
    {
        return $this->groupRepository->transactional(function () use ($command): Result {
            $group = $this->groupRepository->findByIdForUpdate($command->groupId);

            if (null === $group) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            $membership = $group->membershipById($command->membershipId);

            if (null === $membership) {
                return Result::failure([GroupErrors::membershipNotFound()]);
            }

            $this->groupRepository->lockUser($membership->userId());
            $activeGroupCount = $this->groupRepository->countApprovedMemberships($membership->userId());
            $wasActive = $group->isActive();

            $approval = $group->approveMembership(
                membershipId: $command->membershipId,
                actor: $command->actorId,
                activeGroupCount: $activeGroupCount,
            );

            if ($approval->isFailure()) {
                return Result::failure($approval->errors());
            }

            /** @var Membership $approved */
            $approved = $approval->value();

            try {
                $this->groupRepository->save($group);
            } catch (MembershipAlreadyExistsException) {
                return Result::failure([GroupErrors::membershipAlreadyExists()]);
            }

            $this->eventDispatcher->dispatch(MembershipApproved::now(
                groupId: $group->id()->value(),
                membershipId: $approved->id()->value(),
                userId: $approved->userId()->value(),
                decidedBy: $command->actorId->value(),
            ));

            if (!$wasActive && $group->isActive()) {
                $this->eventDispatcher->dispatch(GroupActivated::now($group->id()->value()));
            }

            return Result::success($approved);
        });
    }
}
