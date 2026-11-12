<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Event;

use App\Activities\Domain\Activity\Event\ActivityCancelled;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityReason;
use App\Groups\Domain\Group\Event\GroupArchived;
use App\Groups\Domain\Group\Event\GroupDeleted;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Cancela as atividades agendadas de um grupo arquivado ou excluído.
 *
 * O módulo Activities reage ao evento de domínio de Groups, mantendo a
 * fronteira entre os módulos.
 */
final readonly class GroupLifecycleListener
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
    ) {
    }

    #[AsEventListener(event: 'group.archived')]
    public function onGroupArchived(GroupArchived $event): void
    {
        $this->cancelScheduled($event->groupId(), 'O grupo foi arquivado.');
    }

    #[AsEventListener(event: 'group.deleted')]
    public function onGroupDeleted(GroupDeleted $event): void
    {
        $this->cancelScheduled($event->groupId(), 'O grupo foi excluído.');
    }

    private function cancelScheduled(string $groupId, string $reason): void
    {
        $reasonResult = ActivityReason::fromString($reason);

        if ($reasonResult->isInvalid()) {
            return;
        }

        $activityReason = $reasonResult->value();
        $now = Instant::fromDateTime($this->clock->now());

        foreach ($this->activityRepository->findScheduledIdsByGroup(ActivityGroupId::fromString($groupId)) as $activityId) {
            $this->activityRepository->transactional(function () use ($activityId, $activityReason, $now): void {
                $activity = $this->activityRepository->findByIdForUpdate($activityId);

                if (null === $activity) {
                    return;
                }

                $cancel = $activity->cancel(
                    actor: null,
                    reason: $activityReason,
                    now: $now,
                    automatic: true,
                );

                if ($cancel->isFailure() || false === $cancel->value()) {
                    return;
                }

                $this->activityRepository->save($activity);

                $this->eventDispatcher->dispatch(ActivityCancelled::now(
                    activityId: $activity->id()->value(),
                    groupId: $activity->groupId()->value(),
                    actorId: null,
                    reason: $activityReason->value(),
                    automatic: true,
                ));
            });
        }
    }
}
