<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\CheckIn;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Domain\Activity\Entity\Attendance;
use App\Activities\Domain\Activity\Event\AttendanceCheckedIn;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\ValueObject\AttendanceStatus;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;

final readonly class CheckInHandler
{
    public function __construct(
        private ActivityRepositoryInterface $activityRepository,
        private GroupAccessReaderInterface $groupAccessReader,
        private ActivityAccessPolicy $accessPolicy,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<Attendance>
     */
    public function __invoke(CheckInCommand $command): Result
    {
        return $this->activityRepository->transactional(function () use ($command): Result {
            $activity = $this->activityRepository->findByIdForUpdate($command->activityId);

            if (null === $activity) {
                return Result::failure([ActivityErrors::activityNotFound()]);
            }

            $group = $this->groupAccessReader->snapshot(
                $activity->groupId()->value(),
                $command->userId->value(),
            );
            $accessError = $this->accessPolicy->canParticipate($group);

            if (null !== $accessError) {
                return Result::failure([$accessError]);
            }

            $this->activityRepository->lockUser($command->userId);

            $previousStatus = $activity->attendanceFor($command->userId)?->status();
            $checkIn = $activity->checkIn($command->userId, Instant::now());

            if ($checkIn->isFailure()) {
                return Result::failure($checkIn->errors());
            }

            /** @var Attendance $attendance */
            $attendance = $checkIn->value();

            $changed = AttendanceStatus::ENROLLED === $previousStatus;

            if ($changed) {
                $this->activityRepository->save($activity);
                $this->eventDispatcher->dispatch(AttendanceCheckedIn::now(
                    activityId: $activity->id()->value(),
                    groupId: $activity->groupId()->value(),
                    attendanceId: $attendance->id()->value(),
                    userId: $attendance->userId()->value(),
                ));
            }

            return Result::success($attendance);
        });
    }
}
