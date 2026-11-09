<?php

declare(strict_types=1);

namespace App\Activities\Application\UseCase\ValidateAttendance;

use App\Activities\Application\Service\ActivityAccessPolicy;
use App\Activities\Domain\Activity\Entity\Attendance;
use App\Activities\Domain\Activity\Event\AttendanceValidated;
use App\Activities\Domain\Activity\Repository\ActivityRepositoryInterface;
use App\Activities\Domain\Activity\ValueObject\AttendanceStatus;
use App\Activities\Domain\Error\ActivityErrors;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Application\Group\GroupAccessReaderInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;

final readonly class ValidateAttendanceHandler
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
    public function __invoke(ValidateAttendanceCommand $command): Result
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
            $accessError = $this->accessPolicy->canOperate($group, $command->isAdminSigame);

            if (null !== $accessError) {
                return Result::failure([$accessError]);
            }

            $previousStatus = $activity->attendanceById($command->attendanceId)?->status();
            $validation = $activity->validateAttendance(
                $command->attendanceId,
                $command->actorId,
                Instant::now(),
            );

            if ($validation->isFailure()) {
                return Result::failure($validation->errors());
            }

            /** @var Attendance $attendance */
            $attendance = $validation->value();

            $changed = AttendanceStatus::VALIDATED !== $previousStatus;

            if ($changed) {
                $this->activityRepository->save($activity);
                $this->eventDispatcher->dispatch(AttendanceValidated::now(
                    activityId: $activity->id()->value(),
                    groupId: $activity->groupId()->value(),
                    attendanceId: $attendance->id()->value(),
                    userId: $attendance->userId()->value(),
                    validatedBy: $command->actorId->value(),
                    xpEligible: $activity->isXpEligible(),
                ));
            }

            return Result::success($attendance);
        });
    }
}
