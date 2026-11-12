<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\LeaveGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Event\MembershipLeft;
use App\Groups\Domain\Group\Event\MembershipRequestWithdrawn;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\RemovalReason;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Encerra a participação do próprio usuário no grupo.
 *
 * Solicitações pendentes são canceladas e participações aprovadas são
 * encerradas como saída voluntária. O último administrador não pode sair.
 */
final readonly class LeaveGroupHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<bool> `true` quando havia participação ativa
     */
    public function __invoke(LeaveGroupCommand $command): Result
    {
        return $this->groupRepository->transactional(function () use ($command): Result {
            $group = $this->groupRepository->findByIdForUpdate($command->groupId);

            if (null === $group || $group->isDeleted()) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            $membership = $group->membershipFor($command->actorId);

            if (null === $membership) {
                return Result::success(false);
            }

            $wasPending = $membership->isPending();

            $leave = $group->leave($command->actorId, RemovalReason::voluntaryExit());

            if ($leave->isFailure()) {
                return Result::failure($leave->errors());
            }

            /** @var Membership $left */
            $left = $leave->value();

            $this->groupRepository->save($group);

            $this->eventDispatcher->dispatch($wasPending
                ? MembershipRequestWithdrawn::now(
                    groupId: $group->id()->value(),
                    membershipId: $left->id()->value(),
                    userId: $left->userId()->value(),
                )
                : MembershipLeft::now(
                    groupId: $group->id()->value(),
                    membershipId: $left->id()->value(),
                    userId: $left->userId()->value(),
                    reason: $left->removalReason()?->value() ?? '',
                ));

            return Result::success(true);
        });
    }
}
