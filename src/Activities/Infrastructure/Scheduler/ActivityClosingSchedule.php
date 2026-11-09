<?php

declare(strict_types=1);

namespace App\Activities\Infrastructure\Scheduler;

use App\Activities\Application\UseCase\CloseExpiredActivities\CloseExpiredActivitiesMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('activities')]
final class ActivityClosingSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->with(
            RecurringMessage::every('5 minutes', new CloseExpiredActivitiesMessage()),
        );
    }
}
