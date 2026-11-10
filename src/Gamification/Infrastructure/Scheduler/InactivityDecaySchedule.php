<?php

declare(strict_types=1);

namespace App\Gamification\Infrastructure\Scheduler;

use App\Gamification\Application\UseCase\ApplyInactivityDecay\ApplyInactivityDecayMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Executa a perda por inatividade uma vez por dia.
 */
#[AsSchedule('gamification')]
final class InactivityDecaySchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->with(
            RecurringMessage::every('1 day', new ApplyInactivityDecayMessage()),
        );
    }
}
