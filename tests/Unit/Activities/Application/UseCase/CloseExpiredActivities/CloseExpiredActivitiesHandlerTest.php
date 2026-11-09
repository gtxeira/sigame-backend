<?php

declare(strict_types=1);

use App\Activities\Application\UseCase\CloseExpiredActivities\CloseExpiredActivitiesHandler;
use App\Activities\Application\UseCase\CloseExpiredActivities\CloseExpiredActivitiesMessage;
use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Event\ActivityCancelled;
use App\Activities\Domain\Activity\Event\ActivityFinished;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\ValueObject\ActivityDescription;
use App\Activities\Domain\Activity\ValueObject\ActivityGroupId;
use App\Activities\Domain\Activity\ValueObject\ActivityRoute;
use App\Activities\Domain\Activity\ValueObject\ActivityTitle;
use App\Activities\Domain\Activity\ValueObject\ActivityUserId;
use App\Activities\Domain\Activity\ValueObject\ActivityWindow;
use App\Activities\Domain\Activity\ValueObject\EstimatedDistance;
use App\Activities\Domain\Activity\ValueObject\MeetingPoint;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\ValueObject\Instant;

function closeExpiredActivity(string $startsAt = '+1 hour'): Activity
{
    $window = ActivityWindow::fromStart(
        Instant::fromDateTime(new DateTimeImmutable($startsAt)),
        60,
    )->value();

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

it('cancels activities that were never started', function () {
    $activity = closeExpiredActivity();
    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $dispatcher = Mockery::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('findScheduledToAutoCancel')->once()->andReturn([$activity->id()]);
    $repository->shouldReceive('findStartedToAutoFinish')->once()->andReturn([]);
    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->with($activity->id())->andReturn($activity);
    $repository->shouldReceive('save')->once()->with($activity);
    $dispatcher->shouldReceive('dispatch')->once()->with(Mockery::on(
        static fn (mixed $event): bool => $event instanceof ActivityCancelled && $event->automatic(),
    ));

    $handler = new CloseExpiredActivitiesHandler($repository, $dispatcher);

    expect($handler(new CloseExpiredActivitiesMessage()))->toBe(1)
        ->and($activity->isCancelled())->toBeTrue()
        ->and($activity->cancelledAutomatically())->toBeTrue();
});

it('finishes activities that exceeded the automatic close time', function () {
    $activity = closeExpiredActivity();
    $activity->start(ActivityUserId::generate(), $activity->window()->startsAt());

    $repository = Mockery::mock(ActivityRepositoryInterface::class);
    $dispatcher = Mockery::mock(EventDispatcherInterface::class);

    $repository->shouldReceive('findScheduledToAutoCancel')->once()->andReturn([]);
    $repository->shouldReceive('findStartedToAutoFinish')->once()->andReturn([$activity->id()]);
    $repository->shouldReceive('transactional')->once()->andReturnUsing(
        static fn (callable $operation): mixed => $operation(),
    );
    $repository->shouldReceive('findByIdForUpdate')->once()->with($activity->id())->andReturn($activity);
    $repository->shouldReceive('save')->once()->with($activity);
    $dispatcher->shouldReceive('dispatch')->once()->with(Mockery::on(
        static fn (mixed $event): bool => $event instanceof ActivityFinished && $event->automatic(),
    ));

    $handler = new CloseExpiredActivitiesHandler($repository, $dispatcher);

    expect($handler(new CloseExpiredActivitiesMessage()))->toBe(1)
        ->and($activity->isFinished())->toBeTrue()
        ->and($activity->finishedAutomatically())->toBeTrue();
});
