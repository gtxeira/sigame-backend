<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\CreateActivity;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Event\ActivityCreated;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\ValueObject\ActivityDescription;
use App\Activities\Domain\Activity\ValueObject\ActivityRoute;
use App\Activities\Domain\Activity\ValueObject\ActivityTitle;
use App\Activities\Domain\Activity\ValueObject\ActivityWindow;
use App\Activities\Domain\Activity\ValueObject\EstimatedDistance;
use App\Activities\Domain\Activity\ValueObject\EstimatedDuration;
use App\Activities\Domain\Activity\ValueObject\MeetingPoint;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\Functional\ValidationResult;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Cria uma atividade vinculada a um grupo ativo ou cheio.
 */
final readonly class CreateActivityHandler
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private GroupAccessReaderInterface $groupAccessReader,
        private ActivityAccessPolicy $accessPolicy,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Activity>
     */
    public function __invoke(CreateActivityCommand $command): Result
    {
        $validation = ValidationResult::combine(
            ActivityTitle::fromString($command->title),
            null === $command->description || '' === trim($command->description)
                ? ValidationResult::valid(null)
                : ActivityDescription::fromString($command->description),
            Instant::fromString($command->startsAt, 'startsAt'),
            EstimatedDuration::fromMinutes($command->estimatedDurationMinutes),
            MeetingPoint::fromString($command->meetingPoint),
            ActivityRoute::fromString($command->route),
            EstimatedDistance::fromMeters($command->estimatedDistanceMeters),
        );

        if ($validation->isInvalid()) {
            return Result::failure($validation->errors());
        }

        [
            $title,
            $description,
            $startsAt,
            $estimatedDuration,
            $meetingPoint,
            $route,
            $estimatedDistance,
        ] = $validation->value();

        $windowResult = ActivityWindow::fromStart($startsAt, $estimatedDuration?->minutes());
        $windowErrors = $windowResult->isInvalid() ? $windowResult->errors() : [];

        if (!$startsAt->isAfter(Instant::now())) {
            $windowErrors[] = ActivityErrors::startInPast();
        }

        if ([] !== $windowErrors) {
            return Result::failure($windowErrors);
        }

        /** @var ActivityWindow $activityWindow */
        $activityWindow = $windowResult->value();

        $group = $this->groupAccessReader->snapshot($command->groupId->value(), $command->actorId->value());

        foreach ([
            $this->accessPolicy->canManage($group, $command->isAdminSigame),
            $this->accessPolicy->groupAcceptsActivities($group),
        ] as $error) {
            if (null !== $error) {
                return Result::failure([$error]);
            }
        }

        $activity = Activity::create(
            groupId: $command->groupId,
            createdBy: $command->actorId,
            title: $title,
            description: $description,
            window: $activityWindow,
            meetingPoint: $meetingPoint,
            route: $route,
            estimatedDistance: $estimatedDistance,
        );

        $this->activityRepository->save($activity);

        $this->eventDispatcher->dispatch(ActivityCreated::now(
            activityId: $activity->id()->value(),
            groupId: $activity->groupId()->value(),
            createdBy: $activity->createdBy()->value(),
            startsAt: $activity->window()->startsAt(),
            endsAt: $activity->window()->endsAt(),
        ));

        return Result::success($activity);
    }
}
