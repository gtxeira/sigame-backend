<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ChangeMembershipRole;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Event\MembershipDemoted;
use App\Groups\Domain\Group\Event\MembershipPromoted;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\MembershipRole;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Promove ou rebaixa um membro aprovado.
 *
 * O grupo nunca fica sem ao menos um administrador.
 */
final readonly class ChangeMembershipRoleHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Membership>
     */
    public function __invoke(ChangeMembershipRoleCommand $command): Result
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

            $previousRole = $membership->role();

            $change = MembershipRole::ADMIN === $command->role
                ? $group->promoteToAdmin($command->membershipId, $command->actorId)
                : $group->demoteToMember($command->membershipId, $command->actorId);

            if ($change->isFailure()) {
                return Result::failure($change->errors());
            }

            /** @var Membership $changed */
            $changed = $change->value();

            if ($previousRole === $changed->role()) {
                return Result::success($changed);
            }

            $this->groupRepository->save($group);

            $event = MembershipRole::ADMIN === $changed->role()
                ? MembershipPromoted::now(
                    groupId: $group->id()->value(),
                    membershipId: $changed->id()->value(),
                    userId: $changed->userId()->value(),
                    actorId: $command->actorId->value(),
                )
                : MembershipDemoted::now(
                    groupId: $group->id()->value(),
                    membershipId: $changed->id()->value(),
                    userId: $changed->userId()->value(),
                    actorId: $command->actorId->value(),
                );

            $this->eventDispatcher->dispatch($event);

            return Result::success($changed);
        });
    }
}
