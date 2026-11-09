<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\CreateGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\GroupCreated;
use App\Groups\Domain\Group\Exception\MembershipAlreadyExistsException;
use App\Groups\Domain\Group\Policy\GroupPolicy;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\CommunicationLink;
use App\Groups\Domain\Group\ValueObject\GroupDescription;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\User\UserLevelReaderInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\Functional\ValidationResult;

/**
 * Cria um grupo esportivo.
 *
 * RN04.1: exige nível mínimo 2.
 * RN04.2: o criador inicia como administrador.
 * RN04.3: o usuário não pode ultrapassar o limite de grupos.
 */
final readonly class CreateGroupHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private UserLevelReaderInterface $userLevelReader,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Group>
     */
    public function __invoke(CreateGroupCommand $command): Result
    {
        $validation = ValidationResult::combine(
            GroupName::fromString($command->name),
            null === $command->description
                ? ValidationResult::valid(null)
                : GroupDescription::fromString($command->description),
            null === $command->communicationLink
                ? ValidationResult::valid(null)
                : CommunicationLink::fromString($command->communicationLink),
        );

        if ($validation->isInvalid()) {
            return Result::failure($validation->errors());
        }

        [$name, $description, $communicationLink] = $validation->value();

        return $this->groupRepository->transactional(function () use (
            $command,
            $name,
            $description,
            $communicationLink,
        ): Result {
            $level = $this->userLevelReader->levelOf($command->actorId->value());

            if (null === $level || $level < GroupPolicy::MIN_LEVEL_TO_CREATE) {
                return Result::failure([
                    GroupErrors::levelInsufficient(GroupPolicy::MIN_LEVEL_TO_CREATE),
                ]);
            }

            $this->groupRepository->lockUser($command->actorId);

            if ($this->groupRepository->countApprovedMemberships($command->actorId) >= GroupPolicy::MAX_GROUPS_PER_USER) {
                return Result::failure([
                    GroupErrors::limitReached(GroupPolicy::MAX_GROUPS_PER_USER),
                ]);
            }

            $group = Group::create(
                name: $name,
                description: $description,
                modality: $command->modality,
                communicationLink: $communicationLink,
                createdBy: $command->actorId,
            );

            try {
                $this->groupRepository->save($group);
            } catch (MembershipAlreadyExistsException) {
                return Result::failure([GroupErrors::membershipAlreadyExists()]);
            }

            $this->eventDispatcher->dispatch(GroupCreated::now(
                groupId: $group->id()->value(),
                createdBy: $group->createdBy()->value(),
            ));

            return Result::success($group);
        });
    }
}
