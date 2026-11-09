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
use App\Activities\Domain\Activity\ValueObject\MeetingPoint;
use App\Activities\Domain\Error\ActivityErrorCode;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;
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
                return Result::failure([ActivityErrors::windowInvalid(
                    \App\Activities\Domain\Activity\Policy\ActivityPolicy::MIN_ESTIMATED_DURATION_MINUTES,
                    \App\Activities\Domain\Activity\Policy\ActivityPolicy::MAX_ESTIMATED_DURATION_MINUTES,
                )]);
            }

            $title = $activity->title();
            $description = $activity->description();
            $meetingPoint = $activity->meetingPoint();
            $route = $activity->route();
            $distance = EstimatedDistance::fromMeters($activity->estimatedDistanceMeters())->value();

            if ($command->titleProvided) {
                $result = ActivityTitle::fromString($command->title ?? '');

                if ($result->isInvalid()) {
                    return Result::failure($result->errors());
                }

                $title = $result->value();
            }

            if ($command->descriptionProvided) {
                if (null === $command->description || '' === trim($command->description)) {
                    $description = null;
                } else {
                    $result = ActivityDescription::fromString($command->description);

                    if ($result->isInvalid()) {
                        return Result::failure($result->errors());
                    }

                    $description = $result->value();
                }
            }

            if ($command->meetingPointProvided) {
                $result = MeetingPoint::fromString($command->meetingPoint ?? '');

                if ($result->isInvalid()) {
                    return Result::failure($result->errors());
                }

                $meetingPoint = $result->value();
            }

            if ($command->routeProvided) {
                $result = ActivityRoute::fromString($command->route ?? '');

                if ($result->isInvalid()) {
                    return Result::failure($result->errors());
                }

                $route = $result->value();
            }

            if ($command->distanceProvided) {
                $result = EstimatedDistance::fromMeters($command->estimatedDistanceMeters ?? 0);

                if ($result->isInvalid()) {
                    return Result::failure($result->errors());
                }

                $distance = $result->value();
            }

            $reason = null;

            if (null !== $command->reason && '' !== trim($command->reason)) {
                $reasonResult = ActivityReason::fromString(
                    $command->reason,
                    ActivityErrorCode::ActivityRescheduleReasonInvalid,
                );

                if ($reasonResult->isInvalid()) {
                    return Result::failure($reasonResult->errors());
                }

                $reason = $reasonResult->value();
            }

            $previousStartsAt = $activity->window()->startsAt();

            if ($windowChanged) {
                $startsAt = $activity->window()->startsAt();

                if ($command->startsAtProvided) {
                    $startsAtResult = Instant::fromString($command->startsAt ?? '');

                    if ($startsAtResult->isInvalid()) {
                        return Result::failure($startsAtResult->errors());
                    }

                    $startsAt = $startsAtResult->value();
                }

                $duration = $command->durationProvided
                    ? $command->estimatedDurationMinutes
                    : $activity->window()->estimatedDurationMinutes();

                $windowResult = ActivityWindow::fromStart($startsAt, $duration);

                if ($windowResult->isInvalid()) {
                    return Result::failure($windowResult->errors());
                }

                /** @var ActivityWindow $window */
                $window = $windowResult->value();

                if (!$window->startsAt()->isAfter(Instant::now())) {
                    return Result::failure([ActivityErrors::startInPast()]);
                }

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
                    estimatedDistance: $distance,
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
