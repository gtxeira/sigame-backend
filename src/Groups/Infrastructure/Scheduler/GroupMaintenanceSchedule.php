<?php

declare(strict_types=1);

namespace App\Groups\Infrastructure\Scheduler;

use App\Groups\Application\UseCase\ArchiveIdleFormingGroups\ArchiveIdleFormingGroupsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('groups')]
final class GroupMaintenanceSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->with(
            RecurringMessage::every('1 day', new ArchiveIdleFormingGroupsMessage()),
        );
    }
}
