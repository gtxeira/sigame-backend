<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\EnrollInActivity;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Domain\Activity\Entity\Attendance;
use App\Activities\Domain\Activity\Event\ActivityEnrollmentCreated;
use App\Activities\Domain\Activity\Exception\AttendanceAlreadyExistsException;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;

final readonly class EnrollInActivityHandler
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
    public function __invoke(EnrollInActivityCommand $command): Result
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

            $existing = $activity->attendanceFor($command->userId);

            if (null !== $existing) {
                return Result::success($existing);
            }

            $this->activityRepository->lockUser($command->userId);

            if ($this->activityRepository->hasConflictingAttendance(
                $command->userId,
                $activity->window(),
                $activity->id(),
            )) {
                return Result::failure([ActivityErrors::scheduleConflict()]);
            }

            $enrollment = $activity->enroll($command->userId, Instant::now());

            if ($enrollment->isFailure()) {
                return Result::failure($enrollment->errors());
            }

            /** @var Attendance $attendance */
            $attendance = $enrollment->value();

            try {
                $this->activityRepository->save($activity);
            } catch (AttendanceAlreadyExistsException) {
                return Result::failure([ActivityErrors::alreadyEnrolled()]);
            }

            $this->eventDispatcher->dispatch(ActivityEnrollmentCreated::now(
                activityId: $activity->id()->value(),
                groupId: $activity->groupId()->value(),
                userId: $attendance->userId()->value(),
            ));

            return Result::success($attendance);
        });
    }
}
