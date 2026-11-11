<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\UpdateActivity;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Event\ActivityRescheduled;
use App\Activities\Domain\Activity\Event\ActivityUpdated;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\ValueObject\ActivityDescription;
use App\Activities\Domain\Activity\ValueObject\ActivityReason;
use App\Activities\Domain\Activity\ValueObject\ActivityRoute;
use App\Activities\Domain\Activity\ValueObject\ActivityTitle;
use App\Activities\Domain\Activity\ValueObject\ActivityWindow;
use App\Activities\Domain\Activity\ValueObject\EstimatedDistance;
use App\Activities\Domain\Activity\ValueObject\EstimatedDuration;
use App\Activities\Domain\Activity\ValueObject\MeetingPoint;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Error\ValidationErrors;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\Functional\ValidationResult;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Atualiza dados e/ou remarca uma atividade agendada.
 */
final readonly class UpdateActivityHandler
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
    public function __invoke(UpdateActivityCommand $command): Result
    {
        return $this->activityRepository->transactional(function () use ($command): Result {
            $activity = $this->activityRepository->findByIdForUpdate($command->activityId);

            if (null === $activity) {
                return Result::failure([ActivityErrors::activityNotFound()]);
            }

            $group = $this->groupAccessReader->snapshot(
                $activity->groupId()->value(),
                $command->actorId->value(),
            );
            $accessError = $this->accessPolicy->canManage($group, $command->isAdminSigame);

            if (null !== $accessError) {
                return Result::failure([$accessError]);
            }

            $detailsProvided = $command->titleProvided
                || $command->descriptionProvided
                || $command->meetingPointProvided
                || $command->routeProvided
                || $command->distanceProvided;

            $windowChanged = $command->startsAtProvided || $command->durationProvided;

            if (!$detailsProvided && !$windowChanged) {
                return Result::failure([
                    ValidationErrors::fieldRequired('body', 'Informe ao menos um campo para atualização.'),
                ]);
            }

            $validation = ValidationResult::combine(
                $command->titleProvided
                    ? ActivityTitle::fromString($command->title ?? '')
                    : ValidationResult::valid($activity->title()),
                match (true) {
                    !$command->descriptionProvided => ValidationResult::valid($activity->description()),
                    null === $command->description || '' === trim($command->description) => ValidationResult::valid(null),
                    default => ActivityDescription::fromString($command->description),
                },
                $command->meetingPointProvided
                    ? MeetingPoint::fromString($command->meetingPoint ?? '')
                    : ValidationResult::valid($activity->meetingPoint()),
                $command->routeProvided
                    ? ActivityRoute::fromString($command->route ?? '')
                    : ValidationResult::valid($activity->route()),
                $command->distanceProvided
                    ? EstimatedDistance::fromMeters($command->estimatedDistanceMeters ?? 0)
                    : ValidationResult::valid(EstimatedDistance::fromMeters($activity->estimatedDistanceMeters())->value()),
                $command->startsAtProvided
                    ? Instant::fromString($command->startsAt ?? '', 'startsAt')
                    : ValidationResult::valid($activity->window()->startsAt()),
                $command->durationProvided
                    ? EstimatedDuration::fromMinutes($command->estimatedDurationMinutes)
                    : ValidationResult::valid(EstimatedDuration::fromMinutes($activity->window()->estimatedDurationMinutes())->value()),
                null === $command->reason || '' === trim($command->reason)
                    ? ValidationResult::valid(null)
                    : ActivityReason::fromString($command->reason),
            );

            if ($validation->isInvalid()) {
                return Result::failure($validation->errors());
            }

            [
                $title,
                $description,
                $meetingPoint,
                $route,
                $estimatedDistance,
                $startsAt,
                $estimatedDuration,
                $reason,
            ] = $validation->value();

            $previousStartsAt = $activity->window()->startsAt();

            if ($windowChanged) {
                $windowResult = ActivityWindow::fromStart($startsAt, $estimatedDuration?->minutes());
                $windowErrors = $windowResult->isInvalid() ? $windowResult->errors() : [];

                if (!$startsAt->isAfter(Instant::now())) {
                    $windowErrors[] = ActivityErrors::startInPast();
                }

                if ([] !== $windowErrors) {
                    return Result::failure($windowErrors);
                }

                /** @var ActivityWindow $window */
                $window = $windowResult->value();

                if ([] !== $this->activityRepository->conflictingParticipants($activity->id(), $window)) {
                    return Result::failure([ActivityErrors::rescheduleConflict()]);
                }

                $reschedule = $activity->reschedule(
                    window: $window,
                    actor: $command->actorId,
                    reason: $reason,
                    now: Instant::now(),
                );

                if ($reschedule->isFailure()) {
                    return Result::failure($reschedule->errors());
                }
            }

            if ($detailsProvided) {
                $details = $activity->updateDetails(
                    title: $title,
                    description: $description,
                    meetingPoint: $meetingPoint,
                    route: $route,
                    estimatedDistance: $estimatedDistance,
                );

                if ($details->isFailure()) {
                    return Result::failure($details->errors());
                }
            }

            $this->activityRepository->save($activity);

            if ($detailsProvided) {
                $this->eventDispatcher->dispatch(ActivityUpdated::now(
                    activityId: $activity->id()->value(),
                    groupId: $activity->groupId()->value(),
                    actorId: $command->actorId->value(),
                ));
            }

            if ($windowChanged) {
                $this->eventDispatcher->dispatch(ActivityRescheduled::now(
                    activityId: $activity->id()->value(),
                    groupId: $activity->groupId()->value(),
                    actorId: $command->actorId->value(),
                    previousStartsAt: $previousStartsAt,
                    newStartsAt: $activity->window()->startsAt(),
                ));
            }

            return Result::success($activity);
        });
    }
}
