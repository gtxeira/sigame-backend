<?php

declare(strict_types=1);

namespace App\Activities\Domain\Error;

use App\Shared\Domain\Error\ErrorKind;

/**
 * Catálogo de códigos de erro do módulo Activities.
 *
 * O valor de cada caso é contrato público com o cliente da API:
 * alterá-lo é breaking change; renomear o caso não é.
 */
enum ActivityErrorCode: string
{
    case ActivityNotFound = 'ACTIVITY_NOT_FOUND';
    case ActivityGroupNotFound = 'ACTIVITY_GROUP_NOT_FOUND';
    case ActivityAttendanceNotFound = 'ACTIVITY_ATTENDANCE_NOT_FOUND';
    case ActivityEnrollmentNotFound = 'ACTIVITY_ENROLLMENT_NOT_FOUND';

    case ActivityTitleInvalid = 'ACTIVITY_TITLE_INVALID';
    case ActivityDescriptionInvalid = 'ACTIVITY_DESCRIPTION_INVALID';
    case ActivityMeetingPointInvalid = 'ACTIVITY_MEETING_POINT_INVALID';
    case ActivityRouteInvalid = 'ACTIVITY_ROUTE_INVALID';
    case ActivityEstimatedDistanceInvalid = 'ACTIVITY_ESTIMATED_DISTANCE_INVALID';
    case ActivityWindowInvalid = 'ACTIVITY_WINDOW_INVALID';
    case ActivityStartInPast = 'ACTIVITY_START_IN_PAST';
    case ActivityCancelReasonInvalid = 'ACTIVITY_CANCEL_REASON_INVALID';
    case ActivityRescheduleReasonInvalid = 'ACTIVITY_RESCHEDULE_REASON_INVALID';
    case ActivityInvalidationReasonRequired = 'ACTIVITY_INVALIDATION_REASON_REQUIRED';

    case ActivityPermissionDenied = 'ACTIVITY_PERMISSION_DENIED';
    case ActivityGroupMembershipRequired = 'ACTIVITY_GROUP_MEMBERSHIP_REQUIRED';
    case ActivityEnrollmentRequired = 'ACTIVITY_ENROLLMENT_REQUIRED';

    case ActivityGroupNotActive = 'ACTIVITY_GROUP_NOT_ACTIVE';
    case ActivityNotScheduled = 'ACTIVITY_NOT_SCHEDULED';
    case ActivityNotStarted = 'ACTIVITY_NOT_STARTED';
    case ActivityNotFinished = 'ACTIVITY_NOT_FINISHED';
    case ActivityCancelled = 'ACTIVITY_CANCELLED';
    case ActivityInvalidated = 'ACTIVITY_INVALIDATED';
    case ActivityStartWindowClosed = 'ACTIVITY_START_WINDOW_CLOSED';
    case ActivityFinishWindowClosed = 'ACTIVITY_FINISH_WINDOW_CLOSED';
    case ActivityInvalidationWindowClosed = 'ACTIVITY_INVALIDATION_WINDOW_CLOSED';
    case ActivityEnrollmentNotAllowed = 'ACTIVITY_ENROLLMENT_NOT_ALLOWED';
    case ActivityEnrollmentNotActive = 'ACTIVITY_ENROLLMENT_NOT_ACTIVE';
    case ActivityAlreadyEnrolled = 'ACTIVITY_ALREADY_ENROLLED';
    case ActivityCheckInWindowClosed = 'ACTIVITY_CHECK_IN_WINDOW_CLOSED';
    case ActivityAttendanceNotCheckedIn = 'ACTIVITY_ATTENDANCE_NOT_CHECKED_IN';
    case ActivityScheduleConflict = 'ACTIVITY_SCHEDULE_CONFLICT';
    case ActivityRescheduleConflict = 'ACTIVITY_RESCHEDULE_CONFLICT';
    case ActivityValidationWindowClosed = 'ACTIVITY_VALIDATION_WINDOW_CLOSED';

    public function kind(): ErrorKind
    {
        return match ($this) {
            self::ActivityNotFound,
            self::ActivityGroupNotFound,
            self::ActivityAttendanceNotFound,
            self::ActivityEnrollmentNotFound => ErrorKind::NotFound,

            self::ActivityTitleInvalid,
            self::ActivityDescriptionInvalid,
            self::ActivityMeetingPointInvalid,
            self::ActivityRouteInvalid,
            self::ActivityEstimatedDistanceInvalid,
            self::ActivityWindowInvalid,
            self::ActivityStartInPast,
            self::ActivityCancelReasonInvalid,
            self::ActivityRescheduleReasonInvalid,
            self::ActivityInvalidationReasonRequired => ErrorKind::Validation,

            self::ActivityPermissionDenied,
            self::ActivityGroupMembershipRequired,
            self::ActivityEnrollmentRequired => ErrorKind::Forbidden,

            default => ErrorKind::Conflict,
        };
    }
}
