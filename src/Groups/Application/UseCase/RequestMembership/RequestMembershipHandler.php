<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\RequestMembership;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Membership;
use App\Groups\Domain\Group\Event\MembershipRequested;
use App\Groups\Domain\Group\Exception\MembershipAlreadyExistsException;
use App\Groups\Domain\Group\Policy\GroupPolicy;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\User\UserLevelReaderInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Registra uma solicitação de entrada pendente.
 *
 * RN05.5: exige nível mínimo 2 para solicitar entrada.
 */
final readonly class RequestMembershipHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private UserLevelReaderInterface $userLevelReader,
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

            if (null === $group || $group->isDeleted()) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            $level = $this->userLevelReader->levelOf($command->actorId->value());

            if (null === $level || $level < GroupPolicy::MIN_LEVEL_TO_JOIN) {
                return Result::failure([
                    GroupErrors::levelInsufficientToJoin(GroupPolicy::MIN_LEVEL_TO_JOIN),
                ]);
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
