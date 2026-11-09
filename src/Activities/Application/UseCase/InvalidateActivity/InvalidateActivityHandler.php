<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\InvalidateActivity;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Domain\Activity\Entity\Activity;
use App\Activities\Domain\Activity\Event\ActivityInvalidated;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\ValueObject\ActivityReason;
use App\Activities\Domain\Error\ActivityErrorCode;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;

final readonly class InvalidateActivityHandler
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
    public function __invoke(InvalidateActivityCommand $command): Result
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
            $accessError = $this->accessPolicy->canInvalidate($group, $command->isAdminSigame);

            if (null !== $accessError) {
                return Result::failure([$accessError]);
            }

            $reason = ActivityReason::fromString(
                $command->reason,
                ActivityErrorCode::ActivityInvalidationReasonRequired,
            );

            if ($reason->isInvalid()) {
                return Result::failure($reason->errors());
            }

            $validatedUserIds = array_map(
                static fn ($attendance): string => $attendance->userId()->value(),
                array_filter(
                    $activity->attendances(),
                    static fn ($attendance): bool => $attendance->validatedAt() !== null,
                ),
            );

            $changed = $activity->invalidate(
                actor: $command->actorId,
                reason: $reason->value(),
                now: Instant::now(),
            );

            if ($changed->isFailure()) {
                return Result::failure($changed->errors());
            }

            if (true === $changed->value()) {
                $this->activityRepository->save($activity);
                $this->eventDispatcher->dispatch(ActivityInvalidated::now(
                    activityId: $activity->id()->value(),
                    groupId: $activity->groupId()->value(),
                    actorId: $command->actorId->value(),
                    reason: $activity->invalidationReason() ?? '',
                    validatedUserIds: array_values($validatedUserIds),
                ));
            }

            return Result::success($activity);
        });
    }
}
