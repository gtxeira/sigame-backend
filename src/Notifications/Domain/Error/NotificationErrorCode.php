<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Error;

use App\Shared\Domain\Error\ErrorKind;

/**
 * Catálogo de erros do módulo Notifications.
 */
enum NotificationErrorCode: string
{
    case NotificationNotFound = 'NOTIFICATION_NOT_FOUND';

    public function kind(): ErrorKind
    {
        return match ($this) {
            self::NotificationNotFound => ErrorKind::NotFound,
        };
    }
}
