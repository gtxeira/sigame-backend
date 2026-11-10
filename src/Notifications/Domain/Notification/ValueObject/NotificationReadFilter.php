<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\ValueObject;

/**
 * Filtro de leitura do inbox.
 */
enum NotificationReadFilter: string
{
    case ALL = 'all';
    case UNREAD = 'unread';
    case READ = 'read';
}
