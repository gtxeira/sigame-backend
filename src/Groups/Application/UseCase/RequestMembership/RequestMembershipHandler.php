<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\RequestMembership;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Event\MembershipRequested;
use App\Groups\Domain\Group\Exception\MembershipAlreadyExistsException;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Registra uma solicitação de entrada pendente.
 */
final readonly class RequestMembershipHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Membership>
     */
    public function __invoke(RequestMembershipCommand $command): Result
    {
        return $this->groupRepository->transactional(function () use ($command): Result {
            $group = $this->groupRepository->findByIdForUpdate($command->groupId);

            if (null === $group) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            $request = $group->requestMembership($command->actorId);

            if ($request->isFailure()) {
                return Result::failure($request->errors());
            }

            /** @var Membership $membership */
            $membership = $request->value();

            try {
                $this->groupRepository->save($group);
            } catch (MembershipAlreadyExistsException) {
                return Result::failure([GroupErrors::membershipAlreadyExists()]);
            }

            $this->eventDispatcher->dispatch(MembershipRequested::now(
                groupId: $group->id()->value(),
                membershipId: $membership->id()->value(),
                userId: $membership->userId()->value(),
            ));

            return Result::success($membership);
        });
    }
}
