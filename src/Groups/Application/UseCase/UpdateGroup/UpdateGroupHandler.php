<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\UpdateGroup;

use App\Groups\Domain\Error\GroupErrors;
use App\Groups\Domain\Group\Entity\Group;
use App\Groups\Domain\Group\Event\GroupUpdated;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\CommunicationLink;
use App\Groups\Domain\Group\ValueObject\GroupDescription;
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
        $group = $this->groupRepository->findById($command->groupId);

        if (null === $group) {
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

        $validation = ValidationResult::combine($nameResult, $descriptionResult, $linkResult);

        if ($validation->isInvalid()) {
            return Result::failure($validation->errors());
        }

        [$name, $description, $communicationLink] = $validation->value();
        $modality = null === $command->modality
            ? $group->modality()
            : Modality::from($command->modality);

        return $this->groupRepository->transactional(function () use (
            $command,
            $group,
            $name,
            $description,
            $modality,
            $communicationLink,
        ): Result {
            $update = $group->changeDetails(
                name: $name,
                description: $description,
                modality: $modality,
                communicationLink: $communicationLink,
                actor: $command->actorId,
            );

            if ($update->isFailure()) {
                return Result::failure($update->errors());
            }

            $this->groupRepository->save($group);

            $this->eventDispatcher->dispatch(GroupUpdated::now(
                groupId: $group->id()->value(),
                actorId: $command->actorId->value(),
            ));

            return Result::success($group);
        });
    }
}
