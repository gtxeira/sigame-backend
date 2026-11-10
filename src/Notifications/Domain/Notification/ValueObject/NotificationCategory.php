<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\ValueObject;

/**
 * Agrupamento de notificações para filtros e organização do sino.
 */
enum NotificationCategory: string
{
    case GROUP = 'GROUP';
    case ACTIVITY = 'ACTIVITY';
    case GAMIFICATION = 'GAMIFICATION';
    case ACCOUNT = 'ACCOUNT';
}
