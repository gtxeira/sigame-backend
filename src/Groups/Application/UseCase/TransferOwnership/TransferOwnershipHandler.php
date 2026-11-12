<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\TransferOwnership;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Entity\OwnershipTransfer;
use App\Groups\Domain\Group\Event\GroupOwnershipTransferred;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\Repository\OwnershipTransferRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\User\UserActiveReaderInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Transfere a responsabilidade pelo grupo para um membro aprovado.
 *
 * Apenas o responsável atual pode transferir; o alvo é promovido a
 * administrador e precisa estar com a conta ativa.
 */
final readonly class TransferOwnershipHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private OwnershipTransferRepositoryInterface $ownershipTransferRepository,
        private UserActiveReaderInterface $userActiveReader,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Group>
     */
    public function __invoke(TransferOwnershipCommand $command): Result
    {
        return $this->groupRepository->transactional(function () use ($command): Result {
            $group = $this->groupRepository->findByIdForUpdate($command->groupId);

            if (null === $group || $group->isDeleted()) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            $membership = $group->membershipById($command->membershipId);

            if (null === $membership) {
                return Result::failure([GroupErrors::membershipNotFound()]);
            }

            if (!$group->isOwnedBy($command->actorId)) {
                return Result::failure([GroupErrors::ownerRequired()]);
            }

            if (!$this->userActiveReader->isActive($membership->userId()->value())) {
                return Result::failure([GroupErrors::ownerSuspended()]);
            }

            $previousOwner = $group->ownerId();

            $transfer = $group->transferOwnership($membership->userId(), $command->actorId);

            if ($transfer->isFailure()) {
                return Result::failure($transfer->errors());
            }

            if (false === $transfer->value()) {
                return Result::success($group);
            }

            $this->groupRepository->save($group);

            $newOwner = $group->ownerId();

            $this->ownershipTransferRepository->record(OwnershipTransfer::record(
                group: $group,
                previousOwnerId: $previousOwner,
                newOwnerId: $newOwner,
                actorId: $command->actorId,
            ));

            $this->eventDispatcher->dispatch(GroupOwnershipTransferred::now(
                groupId: $group->id()->value(),
                previousOwnerId: $previousOwner->value(),
                newOwnerId: $newOwner->value(),
                actorId: $command->actorId->value(),
            ));

            return Result::success($group);
        });
    }
}
