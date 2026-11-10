<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Event;

use App\Activities\Domain\Activity\Event\AttendanceValidated;
use App\Gamification\Application\Service\ProgressLeveler;
use App\Gamification\Application\Service\ProgressProvider;
use App\Gamification\Application\Service\WeeklyBonusAwarder;
use App\Gamification\Domain\Progress\Event\XpAwarded;
use App\Gamification\Domain\Progress\Repository\UserProgressRepositoryInterface;
use App\Gamification\Domain\Progress\ValueObject\PlayerId;
use App\Gamification\Domain\Rule\ActivityXpPolicy;
use App\Gamification\Domain\Streak\Service\StreakCalendar;
use App\Gamification\Domain\Xp\Entity\XpEvent;
use App\Gamification\Domain\Xp\Repository\XpEventRepositoryInterface;
use App\Gamification\Domain\Xp\ValueObject\XpKind;
use App\Shared\Application\Activity\ActivityXpReaderInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Exception\InvalidUuidException;
use App\Shared\Domain\ValueObject\Instant;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Concede o XP de uma presença validada e reconcilia os bônus semanais.
 */
#[AsEventListener(event: 'activity.attendance.validated')]
final readonly class AwardActivityXpOnAttendanceValidated
{
    public function __construct(
        private ProgressProvider $progressProvider,
        private UserProgressRepositoryInterface $progressRepository,
        private XpEventRepositoryInterface $xpEventRepository,
        private ActivityXpReaderInterface $activityXpReader,
        private ActivityXpPolicy $activityXpPolicy,
        private StreakCalendar $calendar,
        private WeeklyBonusAwarder $weeklyBonusAwarder,
        private ProgressLeveler $progressLeveler,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(AttendanceValidated $event): void
    {
        if (!$event->xpEligible()) {
            return;
        }

        try {
            $userId = PlayerId::fromString($event->userId());
        } catch (InvalidUuidException) {
            return;
        }

        $idempotencyKey = sprintf('activity:%s', $event->attendanceId());

        if (null !== $this->xpEventRepository->findActiveByIdempotencyKey($idempotencyKey)) {
            return;
        }

        $context = $this->activityXpReader->contextOf($event->activityId());

        if (null === $context) {
            return;
        }

        $progress = $this->progressProvider->getOrCreate($userId);
        $activityDate = $this->calendar->localDate($context->startsAt);
        $weekKey = $this->calendar->weekKey($context->startsAt);
        $dailySequence = $this->xpEventRepository->countActiveActivityEventsOnDate($userId, $activityDate) + 1;
        $amount = $this->activityXpPolicy->calculate($context, $dailySequence);

        $xpEvent = XpEvent::record(
            userId: $userId,
            kind: XpKind::ACTIVITY,
            amount: $amount,
            idempotencyKey: $idempotencyKey,
            sourceType: 'attendance',
            sourceId: $event->attendanceId(),
            activityId: $context->activityId,
            activityDate: $activityDate,
            weekKey: $weekKey,
        );

        $this->xpEventRepository->save($xpEvent);

        if ($amount > 0) {
            $progress->award($amount, true);
        }

        $progress->recordActivityValidation($event->occurredAt());
        $this->progressLeveler->sync($progress);
        $this->progressRepository->save($progress);

        if ($amount > 0) {
            $this->eventDispatcher->dispatch(XpAwarded::now(
                userId: $userId->value(),
                amount: $amount,
                kind: XpKind::ACTIVITY,
                balance: $progress->xpBalance(),
                level: $progress->level(),
            ));
        }

        $activityDates = $this->xpEventRepository->findActiveActivityDates($userId);
        $today = $this->calendar->localDate(Instant::now());

        $this->weeklyBonusAwarder->awardForWeek(
            progress: $progress,
            weekKey: $weekKey,
            activityDates: $activityDates,
            today: $today,
            triggerActivityId: $context->activityId,
            triggerActivityDate: $activityDate,
        );
    }
}
