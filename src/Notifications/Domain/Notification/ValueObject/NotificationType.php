<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\ValueObject;

/**
 * Tipos estáveis de notificação.
 *
 * O valor é contrato público com os clientes (web, nativo e canais externos).
 */
enum NotificationType: string
{
    case MEMBERSHIP_REQUESTED = 'MEMBERSHIP_REQUESTED';
    case MEMBERSHIP_APPROVED = 'MEMBERSHIP_APPROVED';
    case MEMBERSHIP_REJECTED = 'MEMBERSHIP_REJECTED';
    case MEMBER_REMOVED = 'MEMBER_REMOVED';
    case MEMBERSHIP_PROMOTED = 'MEMBERSHIP_PROMOTED';
    case MEMBERSHIP_DEMOTED = 'MEMBERSHIP_DEMOTED';
    case SUPERVISOR_DESIGNATED = 'SUPERVISOR_DESIGNATED';
    case SUPERVISOR_REMOVED = 'SUPERVISOR_REMOVED';
    case GROUP_ACTIVATED = 'GROUP_ACTIVATED';
    case GROUP_ARCHIVED = 'GROUP_ARCHIVED';
    case GROUP_ARCHIVED_OWNER = 'GROUP_ARCHIVED_OWNER';
    case GROUP_OWNER_CHANGED = 'GROUP_OWNER_CHANGED';
    case GROUP_DELETED = 'GROUP_DELETED';
    case ACTIVITY_CREATED = 'ACTIVITY_CREATED';
    case ACTIVITY_UPDATED = 'ACTIVITY_UPDATED';
    case ACTIVITY_RESCHEDULED = 'ACTIVITY_RESCHEDULED';
    case ACTIVITY_CANCELLED = 'ACTIVITY_CANCELLED';
    case ACTIVITY_INVALIDATED = 'ACTIVITY_INVALIDATED';
    case ACTIVITY_FINISHED = 'ACTIVITY_FINISHED';
    case ATTENDANCE_VALIDATED = 'ATTENDANCE_VALIDATED';
    case LEVEL_REACHED = 'LEVEL_REACHED';
    case LEVEL_LOST = 'LEVEL_LOST';
    case WEEKLY_GOAL_COMPLETED = 'WEEKLY_GOAL_COMPLETED';
    case ACCOUNT_SUSPENDED = 'ACCOUNT_SUSPENDED';
    case ACCOUNT_REACTIVATED = 'ACCOUNT_REACTIVATED';

    public function category(): NotificationCategory
    {
        return match ($this) {
            self::MEMBERSHIP_REQUESTED,
            self::MEMBERSHIP_APPROVED,
            self::MEMBERSHIP_REJECTED,
            self::MEMBER_REMOVED,
            self::MEMBERSHIP_PROMOTED,
            self::MEMBERSHIP_DEMOTED,
            self::SUPERVISOR_DESIGNATED,
            self::SUPERVISOR_REMOVED,
            self::GROUP_ACTIVATED,
            self::GROUP_ARCHIVED,
            self::GROUP_ARCHIVED_OWNER,
            self::GROUP_OWNER_CHANGED,
            self::GROUP_DELETED => NotificationCategory::GROUP,

            self::ACTIVITY_CREATED,
            self::ACTIVITY_UPDATED,
            self::ACTIVITY_RESCHEDULED,
            self::ACTIVITY_CANCELLED,
            self::ACTIVITY_INVALIDATED,
            self::ACTIVITY_FINISHED,
            self::ATTENDANCE_VALIDATED => NotificationCategory::ACTIVITY,

            self::LEVEL_REACHED,
            self::LEVEL_LOST,
            self::WEEKLY_GOAL_COMPLETED => NotificationCategory::GAMIFICATION,

            self::ACCOUNT_SUSPENDED,
            self::ACCOUNT_REACTIVATED => NotificationCategory::ACCOUNT,
        };
    }

    public function isAccountEvent(): bool
    {
        return NotificationCategory::ACCOUNT === $this->category();
    }
}
