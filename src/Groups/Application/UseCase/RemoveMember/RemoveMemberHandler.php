<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\RemoveMember;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Event\MemberRemoved;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\RemovalReason;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Remove um membro aprovado, registrando o motivo.
 */
final readonly class RemoveMemberHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Membership>
     */
    public function __invoke(RemoveMemberCommand $command): Result
    {
        $reasonResult = RemovalReason::fromString($command->reason);

        if ($reasonResult->isInvalid()) {
            return Result::failure($reasonResult->errors());
        }

        $reason = $reasonResult->value();

        return $this->groupRepository->transactional(function () use ($command, $reason): Result {
            $group = $this->groupRepository->findByIdForUpdate($command->groupId);

            if (null === $group) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            $removal = $group->removeMembership(
                membershipId: $command->membershipId,
                actor: $command->actorId,
                reason: $reason,
            );

            if ($removal->isFailure()) {
                return Result::failure($removal->errors());
            }

            /** @var Membership $membership */
            $membership = $removal->value();

            $this->groupRepository->save($group);

            $this->eventDispatcher->dispatch(MemberRemoved::now(
                groupId: $group->id()->value(),
                membershipId: $membership->id()->value(),
                userId: $membership->userId()->value(),
                removedBy: $command->actorId->value(),
                reason: $reason->value(),
            ));

            return Result::success($membership);
        });
    }
}
