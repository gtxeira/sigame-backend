<?php

declare(strict_types=1);

namespace App\Gamification\Application\Service;

use App\Gamification\Domain\Progress\Entity\UserProgress;
use App\Gamification\Domain\Progress\Event\StreakUpdated;
use App\Gamification\Domain\Progress\Event\WeeklyGoalCompleted;
use App\Gamification\Domain\Progress\Event\XpAwarded;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Rule\WeeklyXpPolicy;
use App\Gamification\Domain\Streak\Service\StreakCalculator;
use App\Gamification\Domain\Streak\Service\StreakCalendar;
use App\Gamification\Domain\Xp\Entity\XpEvent;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Shared\Application\Event\EventDispatcherInterface;

/**
 * Concede e reconcilia os bônus semanais.
 *
 * A primeira atividade da semana mantém o foguinho; a segunda completa a meta
 * e libera o bônus maior. A reconciliação é usada também após estornos.
 */
final readonly class WeeklyBonusAwarder
{
    public function __construct(
        private UserProgressRepositoryInterface $progressRepository,
        private XpEventRepositoryInterface $xpEventRepository,
        private StreakCalculator $streakCalculator,
        private StreakCalendar $calendar,
        private WeeklyXpPolicy $weeklyXpPolicy,
        private ProgressLeveler $progressLeveler,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param list<string> $activityDates
     */
    public function awardForWeek(
        UserProgress $progress,
        string $weekKey,
        array $activityDates,
        string $today,
        string $triggerActivityId,
        string $triggerActivityDate,
    ): void {
        $weeklyCount = $this->streakCalculator->weeklyCount($activityDates, $weekKey);

        if ($weeklyCount >= 1) {
            $this->awardStart($progress, $weekKey, $triggerActivityId, $triggerActivityDate, $weeklyCount);
        }

        if ($weeklyCount >= 2) {
            $this->awardGoal($progress, $weekKey, $activityDates, $triggerActivityId, $triggerActivityDate, $weeklyCount);
        }

        $current = $this->streakCalculator->current($activityDates, $today);
        $best = $this->streakCalculator->best($activityDates);
        $todayWeek = $this->streakCalculator->weeklyCount(
            $activityDates,
            $this->calendar->weekKeyForDate($today),
        );
        $state = $this->streakCalculator->state($todayWeek, $current);

        $progress->recordWeeklyStreak(
            current: $current,
            best: $best,
            lastCompletedWeek: $weeklyCount >= 2 ? $weekKey : $progress->lastCompletedWeek(),
        );

        $this->progressRepository->save($progress);

        $this->eventDispatcher->dispatch(StreakUpdated::now(
            userId: $progress->userId()->value(),
            current: $current,
            best: $best,
            state: $state,
        ));
    }

    private function awardStart(
        UserProgress $progress,
        string $weekKey,
        string $activityId,
        string $activityDate,
        int $weeklyCount,
    ): void {
        if (null !== $this->xpEventRepository->findActiveWeeklyEvent(
            $progress->userId(),
            $weekKey,
            XpKind::WEEKLY_START,
        )) {
            return;
        }

        $key = sprintf('weekly-start:%s:%s:%s', $progress->userId()->value(), $weekKey, $activityId);

        $amount = $this->weeklyXpPolicy->start();
        $event = XpEvent::record(
            userId: $progress->userId(),
            kind: XpKind::WEEKLY_START,
            amount: $amount,
            idempotencyKey: $key,
            sourceType: 'week',
            sourceId: $weekKey,
            activityId: $activityId,
            activityDate: $activityDate,
            weekKey: $weekKey,
            metadata: [
                'weeklyCount' => $weeklyCount,
                'calculatedAmount' => $amount,
            ],
        );

        $this->xpEventRepository->save($event);
        $progress->award($amount, true);
        $this->progressLeveler->sync($progress);
        $this->progressRepository->save($progress);

        $this->eventDispatcher->dispatch(XpAwarded::now(
            userId: $progress->userId()->value(),
            amount: $amount,
            kind: XpKind::WEEKLY_START,
            balance: $progress->xpBalance(),
            level: $progress->level(),
        ));
    }

    /**
     * @param list<string> $activityDates
     */
    private function awardGoal(
        UserProgress $progress,
        string $weekKey,
        array $activityDates,
        string $activityId,
        string $activityDate,
        int $weeklyCount,
    ): void {
        if (null !== $this->xpEventRepository->findActiveWeeklyEvent(
            $progress->userId(),
            $weekKey,
            XpKind::WEEKLY_GOAL,
        )) {
            return;
        }

        $key = sprintf('weekly-goal:%s:%s:%s', $progress->userId()->value(), $weekKey, $activityId);

        $streak = $this->streakCalculator->current($activityDates, $weekKey);
        $amount = $this->weeklyXpPolicy->goal($streak);
        $event = XpEvent::record(
            userId: $progress->userId(),
            kind: XpKind::WEEKLY_GOAL,
            amount: $amount,
            idempotencyKey: $key,
            sourceType: 'week',
            sourceId: $weekKey,
            activityId: $activityId,
            activityDate: $activityDate,
            weekKey: $weekKey,
            metadata: [
                'weeklyCount' => $weeklyCount,
                'streak' => $streak,
                'calculatedAmount' => $amount,
            ],
        );

        $this->xpEventRepository->save($event);
        $progress->award($amount, true);
        $this->progressLeveler->sync($progress);
        $this->progressRepository->save($progress);

        $this->eventDispatcher->dispatch(XpAwarded::now(
            userId: $progress->userId()->value(),
            amount: $amount,
            kind: XpKind::WEEKLY_GOAL,
            balance: $progress->xpBalance(),
            level: $progress->level(),
        ));

        $this->eventDispatcher->dispatch(WeeklyGoalCompleted::now(
            userId: $progress->userId()->value(),
            weekKey: $weekKey,
            streak: $streak,
            xp: $amount,
        ));
    }
}
