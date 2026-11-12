<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\UpdateGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\GroupUpdated;
use App\Groups\Domain\Group\Exception\DuplicateGroupException;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\CommunicationLink;
use App\Groups\Domain\Group\ValueObject\GroupDescription;
use App\Groups\Domain\Group\ValueObject\GroupMemberLimit;
use App\Groups\Domain\Group\ValueObject\GroupName;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\Functional\ValidationResult;
use App\Shared\Domain\ValueObject\Modality;

/**
 * Atualiza os dados editáveis do grupo, com semântica de JSON Merge Patch.
 */
final readonly class UpdateGroupHandler
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Group>
     */
    public function __invoke(UpdateGroupCommand $command): Result
    {
        return $this->groupRepository->transactional(function () use ($command): Result {
            $group = $this->groupRepository->findByIdForUpdate($command->groupId);

            if (null === $group || $group->isDeleted()) {
                return Result::failure([GroupErrors::groupNotFound()]);
            }

            $nameResult = null === $command->name
                ? ValidationResult::valid($group->name())
                : GroupName::fromString($command->name);

            $descriptionResult = match (true) {
                !$command->descriptionProvided => ValidationResult::valid($group->description()),
                null === $command->description => ValidationResult::valid(null),
                default => GroupDescription::fromString($command->description),
            };

            $linkResult = match (true) {
                !$command->communicationLinkProvided => ValidationResult::valid($group->communicationLink()),
                null === $command->communicationLink => ValidationResult::valid(null),
                default => CommunicationLink::fromString($command->communicationLink),
            };

            $maxMembersResult = match (true) {
                !$command->maxMembersProvided => ValidationResult::valid($group->maxMembers()),
                null === $command->maxMembers => ValidationResult::valid(null),
                default => GroupMemberLimit::fromInt($command->maxMembers),
            };

            $validation = ValidationResult::combine(
                $nameResult,
                $descriptionResult,
                $linkResult,
                $maxMembersResult,
            );

            if ($validation->isInvalid()) {
                return Result::failure($validation->errors());
            }

            [$name, $description, $communicationLink, $maxMembers] = $validation->value();
            $modality = null === $command->modality
                ? $group->modality()
                : Modality::from($command->modality);

            if (!$name->equals($group->name()) || $modality !== $group->modality()) {
                $duplicate = $this->groupRepository->existsByOwnerAndName(
                    owner: $group->ownerId(),
                    name: $name,
                    modality: $modality,
                    excludeGroupId: $group->id(),
                );

                if ($duplicate) {
                    return Result::failure([GroupErrors::duplicateGroup()]);
                }
            }

            $update = $group->changeDetails(
                name: $name,
                description: $description,
                modality: $modality,
                communicationLink: $communicationLink,
                maxMembers: $maxMembers,
                actor: $command->actorId,
            );

            if ($update->isFailure()) {
                return Result::failure($update->errors());
            }

            try {
                $this->groupRepository->save($group);
            } catch (DuplicateGroupException) {
                return Result::failure([GroupErrors::duplicateGroup()]);
            }

            $this->eventDispatcher->dispatch(GroupUpdated::now(
                groupId: $group->id()->value(),
                actorId: $command->actorId->value(),
            ));

            return Result::success($group);
        });
    }
}
