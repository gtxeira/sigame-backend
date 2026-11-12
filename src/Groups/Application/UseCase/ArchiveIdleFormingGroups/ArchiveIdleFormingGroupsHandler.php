<?php

declare(strict_types=1);

namespace App\Groups\Application\UseCase\ArchiveIdleFormingGroups;

use App\Groups\Domain\Group\Event\GroupArchived;
use App\Groups\Domain\Group\Policy\GroupPolicy;
use App\Groups\Domain\Group\Repository\GroupRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;

/**
 * Arquiva grupos que permanecem em formação sem atividade pelo período
 * configurado em `GroupPolicy::FORMING_IDLE_DAYS`.
 *
 * A operação é idempotente e revalida o estado do agregado sob lock antes de
 * arquivar, para não depender apenas do índice de consulta.
 */
final readonly class ArchiveIdleFormingGroupsHandler
{
    private const string AUTOMATIC_REASON = 'Arquivado automaticamente por inatividade.';

    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ArchiveIdleFormingGroupsMessage $message): int
    {
        $now = Instant::fromDateTime($this->clock->now());
        $cutoff = $now->subDays(GroupPolicy::FORMING_IDLE_DAYS);

        if ($cutoff->isInvalid()) {
            return 0;
        }

        /** @var Instant $idleSince */
        $idleSince = $cutoff->value();
        $archived = 0;

        foreach ($this->groupRepository->findIdleFormingGroupIds($idleSince) as $groupId) {
            $changed = $this->groupRepository->transactional(function () use ($groupId, $idleSince, $now): bool {
                $group = $this->groupRepository->findByIdForUpdate($groupId);

                if (null === $group || $group->isDeleted() || $group->isArchived()) {
                    return false;
                }

                if (null !== $group->activatedAt()) {
                    return false;
                }

                if ($group->approvedCount() >= GroupPolicy::MIN_APPROVED_FOR_ACTIVE) {
                    return false;
                }

                if (!$group->updatedAt()->isBefore($idleSince)) {
                    return false;
                }

                $archive = $group->archive(
                    actor: null,
                    reason: self::AUTOMATIC_REASON,
                    automatic: true,
                    at: $now,
                );

                if ($archive->isFailure() || false === $archive->value()) {
                    return false;
                }

                $this->groupRepository->save($group);

                $this->eventDispatcher->dispatch(GroupArchived::now(
                    groupId: $group->id()->value(),
                    createdBy: $group->createdBy()->value(),
                    archivedBy: null,
                    reason: $group->archiveReason(),
                    automatic: true,
                ));

                return true;
            });

            if ($changed) {
                ++$archived;
            }
        }

        return $archived;
    }
}
