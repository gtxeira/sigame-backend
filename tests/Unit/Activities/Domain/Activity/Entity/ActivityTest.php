<?php

declare(strict_types=1);

use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Entity\Attendance;
use App\Activities\Domain\Activity\Policy\ActivityPolicy;
use App\Activities\Domain\Activity\ValueObject\ActivityDescription;
use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityReason;
use App\Activities\Domain\Activity\ValueObject\ActivityRoute;
use App\Activities\Domain\Activity\ValueObject\ActivityTitle;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Domain\Activity\ValueObject\ActivityWindow;
use App\Activities\Domain\Activity\ValueObject\AttendanceStatus;
use App\Activities\Domain\Activity\ValueObject\EstimatedDistance;
use App\Activities\Domain\Activity\ValueObject\MeetingPoint;
use App\Shared\Domain\ValueObject\Instant;

function activityTestInstant(string $modifier = '+1 hour'): Instant
{
    return Instant::fromDateTime(new DateTimeImmutable($modifier));
}

function activityTestBuild(?int $duration = 60, string $startsAt = '+1 hour'): Activity
{
    $window = ActivityWindow::fromStart(activityTestInstant($startsAt), $duration)->value();

    return Activity::create(
        groupId: ActivityGroupId::generate(),
        createdBy: ActivityUserId::generate(),
        title: ActivityTitle::fromString('Corrida no Parque')->value(),
        description: ActivityDescription::fromString('Encontro de sábado')->value(),
        window: $window,
        meetingPoint: MeetingPoint::fromString('Portão principal')->value(),
        route: ActivityRoute::fromString('Volta completa na pista')->value(),
        estimatedDistance: EstimatedDistance::fromMeters(5000)->value(),
    );
}

it('creates a scheduled activity and uses the default planning window when duration is omitted', function () {
    $activity = activityTestBuild(null);

    expect($activity->status()->value)->toBe('scheduled')
        ->and($activity->window()->estimatedDurationMinutes())->toBeNull()
        ->and($activity->window()->plannedDurationMinutes())->toBe(ActivityPolicy::DEFAULT_CONFLICT_WINDOW_MINUTES)
        ->and($activity->window()->endsAt()->isAfter($activity->window()->startsAt()))->toBeTrue();
});

it('reschedules a scheduled activity and preserves the previous window', function () {
    $activity = activityTestBuild();
    $previousStartsAt = $activity->window()->startsAt();
    $newWindow = ActivityWindow::fromStart(activityTestInstant('+2 hours'), 90)->value();

    $result = $activity->reschedule(
        window: $newWindow,
        actor: ActivityUserId::generate(),
        reason: ActivityReason::fromString('Mudança de local')->value(),
        now: Instant::now(),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($activity->reschedules())->toHaveCount(1)
        ->and($activity->reschedules()[0]->previousStartsAt()->equals($previousStartsAt))->toBeTrue()
        ->and($activity->window()->estimatedDurationMinutes())->toBe(90);
});

it('starts within the window and computes the automatic close time', function () {
    $activity = activityTestBuild(60, '+1 hour');
    $now = $activity->window()->startsAt();

    $started = $activity->start(ActivityUserId::generate(), $now);

    expect($started->value())->toBeTrue()
        ->and($activity->isStarted())->toBeTrue()
        ->and($activity->autoCloseAt())->not->toBeNull()
        ->and($activity->autoCloseAt()?->timestamp())->toBe($now->timestamp() + (90 * 60));
});

it('rejects starting outside the operational window', function () {
    $activity = activityTestBuild(60, '+10 hours');

    $result = $activity->start(ActivityUserId::generate(), Instant::now());

    expect($result->isFailure())->toBeTrue()
        ->and($result->errors()[0]->code)->toBe('ACTIVITY_START_WINDOW_CLOSED');
});

it('enrolls, checks in and validates an attendance once', function () {
    $activity = activityTestBuild(60, '+1 hour');
    $user = ActivityUserId::generate();
    $now = $activity->window()->startsAt()->value()->modify('+5 minutes');
    $nowInstant = Instant::fromDateTime($now);

    $enrollment = $activity->enroll($user, Instant::now());
    expect($enrollment->isSuccess())->toBeTrue();

    /** @var Attendance $attendance */
    $attendance = $enrollment->value();

    $activity->start(ActivityUserId::generate(), $activity->window()->startsAt());
    $checkIn = $activity->checkIn($user, $nowInstant);
    expect($checkIn->isSuccess())->toBeTrue()
        ->and($attendance->status())->toBe(AttendanceStatus::CHECKED_IN);

    $activity->finish(ActivityUserId::generate(), $nowInstant);

    // A validação só abre depois do fechamento do check-in.
    $validationTime = Instant::fromDateTime(
        $activity->window()->endsAt()->value()->modify('+40 minutes'),
    );

    $validated = $activity->validateAttendance($attendance->id(), ActivityUserId::generate(), $validationTime);
    expect($validated->isSuccess())->toBeTrue()
        ->and($attendance->status())->toBe(AttendanceStatus::VALIDATED);

    $again = $activity->validateAttendance($attendance->id(), ActivityUserId::generate(), $validationTime);
    expect($again->isSuccess())->toBeTrue()
        ->and($activity->validatedCount())->toBe(1);
});

it('requires at least two check-ins for xp eligibility', function () {
    $activity = activityTestBuild(60, '+1 hour');
    $first = ActivityUserId::generate();
    $second = ActivityUserId::generate();

    $activity->enroll($first, Instant::now());
    $activity->enroll($second, Instant::now());
    $activity->start(ActivityUserId::generate(), $activity->window()->startsAt());

    $checkInAt = Instant::fromDateTime($activity->window()->startsAt()->value()->modify('+5 minutes'));
    $activity->checkIn($first, $checkInAt);

    $activity->finish(ActivityUserId::generate(), $checkInAt);

    expect($activity->checkedInCount())->toBe(1)
        ->and($activity->isXpEligible())->toBeFalse();

    $activity->enroll(ActivityUserId::generate(), Instant::now());

    expect($activity->isXpEligible())->toBeFalse();
});

it('cancels an activity and makes it terminal', function () {
    $activity = activityTestBuild();
    $actor = ActivityUserId::generate();

    $cancelled = $activity->cancel($actor, null, Instant::now());

    expect($cancelled->value())->toBeTrue()
        ->and($activity->isCancelled())->toBeTrue()
        ->and($activity->cancel($actor, null, Instant::now())->value())->toBeFalse()
        ->and($activity->start($actor, $activity->window()->startsAt())->isFailure())->toBeTrue();
});

it('invalidates a finished activity and records the reason', function () {
    $activity = activityTestBuild(60, '+1 hour');
    $actor = ActivityUserId::generate();
    $activity->start($actor, $activity->window()->startsAt());
    $activity->finish($actor, Instant::fromDateTime($activity->window()->endsAt()->value()->modify('+5 minutes')));

    $result = $activity->invalidate(
        $actor,
        ActivityReason::fromString('Registro duplicado')->value(),
        Instant::now(),
    );

    expect($result->isSuccess())->toBeTrue()
        ->and($activity->isInvalidated())->toBeTrue()
        ->and($activity->invalidationReason())->toBe('Registro duplicado');
});
