<?php

declare(strict_types=1);

namespace App\Groups\Infrastructure\Group;

use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Groups\Domain\Group\ValueObject\GroupId;
use App\Shared\Application\Group\GroupSummary;
use App\Shared\Application\Group\GroupSummaryReaderInterface;
use App\Shared\Domain\Exception\InvalidUuidException;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Adapter Doctrine do resumo de grupo para notificações.
 */
final readonly class DoctrineGroupSummaryReader implements GroupSummaryReaderInterface
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private ManagerRegistry $registry,
    ) {
    }

    public function summary(string $groupId): ?GroupSummary
    {
        try {
            $id = GroupId::fromString($groupId);
        } catch (InvalidUuidException) {
            return null;
        }

        $group = $this->groupRepository->findById($id);

        if (null === $group) {
            return null;
        }

        $adminIds = [];
        $approvedMemberIds = [];

        foreach ($group->memberships() as $membership) {
            if (!$membership->isApproved()) {
                continue;
            }

            $userId = $membership->userId()->value();
            $approvedMemberIds[] = $userId;

            if ($membership->isAdmin()) {
                $adminIds[] = $userId;
            }
        }

        return new GroupSummary(
            id: $group->id()->value(),
            name: $group->name()->value(),
            adminIds: array_values(array_unique($adminIds)),
            supervisorId: $group->supervisor()?->userId()->value(),
            approvedMemberIds: array_values(array_unique($approvedMemberIds)),
            ownerId: $group->ownerId()->value(),
        );
    }

    public function names(array $groupIds): array
    {
        $ids = array_values(array_unique(array_filter(
            $groupIds,
            static fn (string $id): bool => '' !== $id,
        )));

        if ([] === $ids) {
            return [];
        }

        /** @var list<array{id: string, name: string}> $rows */
        $rows = $this->registry->getConnection()->fetchAllAssociative(
            'SELECT id, name FROM groups WHERE id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );

        $names = [];

        foreach ($rows as $row) {
            $names[(string) $row['id']] = (string) $row['name'];
        }

        return $names;
    }
}
