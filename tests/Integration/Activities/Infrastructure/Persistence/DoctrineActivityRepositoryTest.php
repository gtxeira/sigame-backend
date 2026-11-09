<?php

declare(strict_types=1);

use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\Repository\ActivitySearchCriteria;
use App\Activities\Domain\Activity\ValueObject\ActivityDescription;
use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityRoute;
use App\Activities\Domain\Activity\ValueObject\ActivityTitle;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Domain\Activity\ValueObject\ActivityWindow;
use App\Activities\Domain\Activity\ValueObject\EstimatedDistance;
use App\Activities\Domain\Activity\ValueObject\MeetingPoint;
use App\Shared\Domain\ValueObject\Instant;
use Doctrine\ORM\EntityManagerInterface;

function makeRepositoryActivity(
    string $startsAt = '+1 hour',
    ?int $duration = 60,
    ?ActivityGroupId $groupId = null,
): Activity {
    $window = ActivityWindow::fromStart(
        Instant::fromDateTime(new DateTimeImmutable($startsAt)),
        $duration,
    )->value();

    return Activity::create(
        groupId: $groupId ?? ActivityGroupId::generate(),
        createdBy: ActivityUserId::generate(),
        title: ActivityTitle::fromString('Corrida no Parque')->value(),
        description: ActivityDescription::fromString('Encontro de sábado')->value(),
        window: $window,
        meetingPoint: MeetingPoint::fromString('Portão principal')->value(),
        route: ActivityRoute::fromString('Volta completa na pista')->value(),
        estimatedDistance: EstimatedDistance::fromMeters(5000)->value(),
    );
}

it('persists and reloads an activity with attendances and reschedules', function () {
    $repository = $this->service(ActivityRepositoryInterface::class);
    $entityManager = $this->service(EntityManagerInterface::class);

    $activity = makeRepositoryActivity();
    $user = ActivityUserId::generate();
    $activity->enroll($user, Instant::now());
    $newWindow = ActivityWindow::fromStart(
        Instant::fromDateTime(new DateTimeImmutable('+2 hours')),
        90,
    )->value();
    $activity->reschedule(
        $newWindow,
        $activity->createdBy(),
        null,
        Instant::now(),
    );

    $repository->save($activity);
    $entityManager->clear();

    $found = $repository->findById($activity->id());

    expect($found)->not->toBeNull()
        ->and($found?->attendances())->toHaveCount(1)
        ->and($found?->reschedules())->toHaveCount(1)
        ->and($found?->window()->estimatedDurationMinutes())->toBe(90);
});

it('finds conflicting attendances and ignores adjacent windows', function () {
    $repository = $this->service(ActivityRepositoryInterface::class);
    $user = ActivityUserId::generate();

    $first = makeRepositoryActivity('+1 hour', 60);
    $first->enroll($user, Instant::now());
    $repository->save($first);

    $overlap = ActivityWindow::fromStart(
        Instant::fromDateTime(new DateTimeImmutable('+1 hour 30 minutes')),
        60,
    )->value();
    $adjacent = ActivityWindow::fromStart(
        Instant::fromDateTime(new DateTimeImmutable('+2 hours')),
        60,
    )->value();

    expect($repository->hasConflictingAttendance($user, $overlap))->toBeTrue()
        ->and($repository->hasConflictingAttendance($user, $adjacent))->toBeFalse()
        ->and($repository->conflictingParticipants($first->id(), $overlap))->toBe([]);
});

it('finds activities due for automatic cancellation and finishing', function () {
    $repository = $this->service(ActivityRepositoryInterface::class);

    $toCancel = makeRepositoryActivity('-4 hours', 60);
    $repository->save($toCancel);

    $toFinish = makeRepositoryActivity('-2 hours', 15);
    $toFinish->start($toFinish->createdBy(), Instant::fromDateTime(new DateTimeImmutable('-1 hour')));
    $repository->save($toFinish);

    $now = Instant::now();

    expect($repository->findScheduledToAutoCancel($now))->toHaveCount(1)
        ->and($repository->findStartedToAutoFinish($now))->toHaveCount(1)
        ->and($repository->findScheduledToAutoCancel($now)[0]->equals($toCancel->id()))->toBeTrue()
        ->and($repository->findStartedToAutoFinish($now)[0]->equals($toFinish->id()))->toBeTrue();
});

it('filters activities by group and participant', function () {
    $repository = $this->service(ActivityRepositoryInterface::class);
    $group = ActivityGroupId::generate();
    $user = ActivityUserId::generate();

    $first = makeRepositoryActivity('+1 hour', 60, $group);
    $first->enroll($user, Instant::now());
    $repository->save($first);

    $second = makeRepositoryActivity('+5 hours', 60, $group);
    $repository->save($second);

    $byGroup = $repository->searchByGroup($group, new ActivitySearchCriteria());
    $byParticipant = $repository->searchByParticipant($user, new ActivitySearchCriteria());

    expect($byGroup->total)->toBe(2)
        ->and($byParticipant->total)->toBe(1)
        ->and($byParticipant->items[0]->id()->equals($first->id()))->toBeTrue();
});
