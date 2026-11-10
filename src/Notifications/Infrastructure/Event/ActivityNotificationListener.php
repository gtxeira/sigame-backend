<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Event;

use App\Activities\Domain\Activity\Event\ActivityCancelled;
use App\Activities\Domain\Activity\Event\ActivityCreated;
use App\Activities\Domain\Activity\Event\ActivityFinished;
use App\Activities\Domain\Activity\Event\ActivityInvalidated;
use App\Activities\Domain\Activity\Event\ActivityRescheduled;
use App\Activities\Domain\Activity\Event\ActivityUpdated;
use App\Activities\Domain\Activity\Event\AttendanceValidated;
use App\Notifications\Application\Service\NotificationIntent;
use App\Notifications\Application\Service\NotificationRequestPublisher;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Traduz eventos de atividades em intenções de notificação.
 */
final readonly class ActivityNotificationListener
{
    public function __construct(
        private NotificationRequestPublisher $publisher,
    ) {
    }

    #[AsEventListener(event: 'activity.created')]
    public function onActivityCreated(ActivityCreated $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::ACTIVITY_CREATED,
            deduplicationKey: 'activity-created:'.$event->activityId(),
            context: [
                'activityId' => $event->activityId(),
                'groupId' => $event->groupId(),
                'createdBy' => $event->createdBy(),
            ],
            actorId: $event->createdBy(),
        ));
    }

    #[AsEventListener(event: 'activity.updated')]
    public function onActivityUpdated(ActivityUpdated $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::ACTIVITY_UPDATED,
            deduplicationKey: 'activity-updated:'.$event->activityId().':'.$event->occurredAt()->toIsoStringWithMicroseconds(),
            context: [
                'activityId' => $event->activityId(),
                'groupId' => $event->groupId(),
                'actorId' => $event->actorId(),
            ],
            actorId: $event->actorId(),
        ));
    }

    #[AsEventListener(event: 'activity.rescheduled')]
    public function onActivityRescheduled(ActivityRescheduled $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::ACTIVITY_RESCHEDULED,
            deduplicationKey: 'activity-rescheduled:'.$event->activityId().':'.$event->newStartsAt()->toIsoStringWithMilliseconds(),
            context: [
                'activityId' => $event->activityId(),
                'groupId' => $event->groupId(),
                'actorId' => $event->actorId(),
                'previousStartsAt' => $event->previousStartsAt()->toIsoStringWithMilliseconds(),
                'newStartsAt' => $event->newStartsAt()->toIsoStringWithMilliseconds(),
            ],
            actorId: $event->actorId(),
        ));
    }

    #[AsEventListener(event: 'activity.cancelled')]
    public function onActivityCancelled(ActivityCancelled $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::ACTIVITY_CANCELLED,
            deduplicationKey: 'activity-cancelled:'.$event->activityId(),
            context: [
                'activityId' => $event->activityId(),
                'groupId' => $event->groupId(),
                'actorId' => $event->actorId(),
                'reason' => $event->reason(),
                'automatic' => $event->automatic(),
            ],
            actorId: $event->actorId(),
            automatic: $event->automatic(),
        ));
    }

    #[AsEventListener(event: 'activity.invalidated')]
    public function onActivityInvalidated(ActivityInvalidated $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::ACTIVITY_INVALIDATED,
            deduplicationKey: 'activity-invalidated:'.$event->activityId(),
            context: [
                'activityId' => $event->activityId(),
                'groupId' => $event->groupId(),
                'actorId' => $event->actorId(),
                'reason' => $event->reason(),
                'automatic' => false,
            ],
            actorId: $event->actorId(),
        ));
    }

    #[AsEventListener(event: 'activity.finished')]
    public function onActivityFinished(ActivityFinished $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::ACTIVITY_FINISHED,
            deduplicationKey: 'activity-finished:'.$event->activityId(),
            context: [
                'activityId' => $event->activityId(),
                'groupId' => $event->groupId(),
                'actorId' => $event->actorId(),
                'automatic' => $event->automatic(),
            ],
            actorId: $event->actorId(),
            automatic: $event->automatic(),
        ));
    }

    #[AsEventListener(event: 'activity.attendance.validated')]
    public function onAttendanceValidated(AttendanceValidated $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::ATTENDANCE_VALIDATED,
            deduplicationKey: 'attendance-validated:'.$event->attendanceId(),
            context: [
                'activityId' => $event->activityId(),
                'groupId' => $event->groupId(),
                'attendanceId' => $event->attendanceId(),
                'userId' => $event->userId(),
                'validatedBy' => $event->validatedBy(),
                'xpEligible' => $event->xpEligible(),
            ],
            actorId: $event->validatedBy(),
        ));
    }
}
