<?php

declare(strict_types=1);

namespace App\Gamification\Domain\Streak\ValueObject;

/**
 * Estado visual do foguinho semanal.
 */
enum WeeklyStreakState: string
{
    case UNLIT = 'APAGADO';
    case LIT = 'ACESO';
    case AT_RISK = 'EM_RISCO';
    case COMPLETE = 'COMPLETO';
}
