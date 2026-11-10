<?php

declare(strict_types=1);

namespace App\Groups\Infrastructure\Group;

use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Shared\Application\Group\GroupModalityReaderInterface;
use App\Shared\Domain\Exception\InvalidUuidException;
use App\Shared\Domain\ValueObject\Modality;

/**
 * Adapter Doctrine da porta de modalidade do grupo.
 */
final readonly class DoctrineGroupModalityReader implements GroupModalityReaderInterface
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
    ) {
    }

    public function modalityOf(string $groupId): ?Modality
    {
        try {
            $group = $this->groupRepository->findById(GroupId::fromString($groupId));
        } catch (InvalidUuidException) {
            return null;
        }

        return $group?->modality();
    }
}
