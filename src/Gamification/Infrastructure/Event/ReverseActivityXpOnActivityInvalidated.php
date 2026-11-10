<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Event;

use App\Activities\Domain\Activity\Event\ActivityInvalidated;
use App\Gamification\Application\Service\ProgressLeveler;
use App\Gamification\Application\Service\WeeklyBonusAwarder;
use App\Gamification\Domain\Progress\Event\XpAwarded;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Streak\Service\StreakCalendar;
use App\Gamification\Domain\Xp\Entity\XpEvent;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Exception\InvalidUuidException;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Estorna todo o XP vinculado a uma atividade invalidada.
 */
#[AsEventListener(event: 'activity.invalidated')]
final readonly class ReverseActivityXpOnActivityInvalidated
{
    public function __construct(
        private UserProgressRepositoryInterface $progressRepository,
        private XpEventRepositoryInterface $xpEventRepository,
        private WeeklyBonusAwarder $weeklyBonusAwarder,
        private ProgressLeveler $progressLeveler,
        private StreakCalendar $calendar,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ActivityInvalidated $event): void
    {
        foreach ($event->validatedUserIds() as $validatedUserId) {
            try {
                $userId = PlayerId::fromString($validatedUserId);
            } catch (InvalidUuidException) {
                continue;
            }

            $progress = $this->progressRepository->findByUserId($userId);

            if (null === $progress) {
                continue;
            }

            $events = $this->xpEventRepository->findActiveByActivityAndUser($event->activityId(), $userId);

            if ([] === $events) {
                continue;
            }

            $weekKeys = [];

            foreach ($events as $original) {
                if (null !== $original->weekKey()) {
                    $weekKeys[$original->weekKey()] = true;
                }

                $reversalKey = sprintf('reversal:%s', $original->id()->value());

                if (null !== $this->xpEventRepository->findActiveByIdempotencyKey($reversalKey)) {
                    continue;
                }

                $reversal = XpEvent::record(
                    userId: $userId,
                    kind: XpKind::REVERSAL,
                    amount: -$original->amount(),
                    idempotencyKey: $reversalKey,
                    sourceType: $original->sourceType(),
                    sourceId: $original->sourceId(),
                    activityId: $original->activityId(),
                    activityDate: $original->activityDate(),
                    weekKey: $original->weekKey(),
                    metadata: ['reversalOf' => $original->id()->value()],
                    reversalOfId: $original->id(),
                );

                $this->xpEventRepository->save($reversal);
                $original->markReversed(Instant::now());
                $this->xpEventRepository->save($original);
                $progress->award(-$original->amount(), $original->kind()->affectsLifetime());

                $this->eventDispatcher->dispatch(XpAwarded::now(
                    userId: $userId->value(),
                    amount: -$original->amount(),
                    kind: XpKind::REVERSAL,
                    balance: $progress->xpBalance(),
                    level: $progress->level(),
                ));
            }

            $activityDates = $this->xpEventRepository->findActiveActivityDates($userId);
            $remainingActivities = $this->xpEventRepository->findActiveActivityEvents($userId);
            $today = $this->calendar->localDate(Instant::now());

            foreach (array_keys($weekKeys) as $weekKey) {
                $trigger = $this->firstActivityOfWeek($remainingActivities, $weekKey);

                if (null === $trigger) {
                    continue;
                }

                $this->weeklyBonusAwarder->awardForWeek(
                    progress: $progress,
                    weekKey: $weekKey,
                    activityDates: $activityDates,
                    today: $today,
                    triggerActivityId: (string) $trigger->activityId(),
                    triggerActivityDate: (string) $trigger->activityDate(),
                );
            }

            $this->progressLeveler->sync($progress, 'activity_invalidated');
            $this->progressRepository->save($progress);
        }
    }

    /**
     * @param list<XpEvent> $events
     */
    private function firstActivityOfWeek(array $events, string $weekKey): ?XpEvent
    {
        foreach ($events as $event) {
            if ($event->weekKey() === $weekKey) {
                return $event;
            }
        }

        return null;
    }
}
