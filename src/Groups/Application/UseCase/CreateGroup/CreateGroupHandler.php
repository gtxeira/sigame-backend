<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\CreateGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\GroupCreated;
use App\Groups\Domain\Group\Exception\DuplicateGroupException;
use App\Groups\Domain\Group\Exception\MembershipAlreadyExistsException;
use App\Groups\Domain\Group\Policy\GroupPolicy;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\CommunicationLink;
use App\Groups\Domain\Group\ValueObject\GroupDescription;
use App\Groups\Domain\Group\ValueObject\GroupMemberLimit;
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
 * RN04.3: o usuário não pode ultrapassar o limite de participação.
 * RN04.10: o usuário pode ter apenas um grupo em formação por vez.
 * RN04.11: nome e modalidade não podem repetir entre os grupos do criador.
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
            null === $command->maxMembers
                ? ValidationResult::valid(null)
                : GroupMemberLimit::fromInt($command->maxMembers),
        );

        if ($validation->isInvalid()) {
            return Result::failure($validation->errors());
        }

        [$name, $description, $communicationLink, $maxMembers] = $validation->value();

        return $this->groupRepository->transactional(function () use (
            $command,
            $name,
            $description,
            $communicationLink,
            $maxMembers,
        ): Result {
            $level = $this->userLevelReader->levelOf($command->actorId->value());

            if (null === $level || $level < GroupPolicy::MIN_LEVEL_TO_CREATE) {
                return Result::failure([
                    GroupErrors::levelInsufficient(GroupPolicy::MIN_LEVEL_TO_CREATE),
                ]);
            }

            $this->groupRepository->lockUser($command->actorId);

            if ($this->groupRepository->existsByCreatorAndName($command->actorId, $name, $command->modality)) {
                return Result::failure([GroupErrors::duplicateGroup()]);
            }

            if ($this->groupRepository->countApprovedMemberships($command->actorId) >= GroupPolicy::MAX_PARTICIPATION_PER_USER) {
                return Result::failure([
                    GroupErrors::limitReached(GroupPolicy::MAX_PARTICIPATION_PER_USER),
                ]);
            }

            if ($this->groupRepository->countFormingCreatedBy($command->actorId) >= GroupPolicy::MAX_FORMING_PER_CREATOR) {
                return Result::failure([
                    GroupErrors::formingLimitReached(GroupPolicy::MAX_FORMING_PER_CREATOR),
                ]);
            }

            $group = Group::create(
                name: $name,
                description: $description,
                modality: $command->modality,
                communicationLink: $communicationLink,
                createdBy: $command->actorId,
                maxMembers: $maxMembers,
            );

            try {
                $this->groupRepository->save($group);
            } catch (MembershipAlreadyExistsException) {
                return Result::failure([GroupErrors::membershipAlreadyExists()]);
            } catch (DuplicateGroupException) {
                return Result::failure([GroupErrors::duplicateGroup()]);
            }

            $this->eventDispatcher->dispatch(GroupCreated::now(
                groupId: $group->id()->value(),
                createdBy: $group->createdBy()->value(),
            ));

            return Result::success($group);
        });
    }
}
