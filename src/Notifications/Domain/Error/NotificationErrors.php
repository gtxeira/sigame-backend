<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Error;

use App\Shared\Domain\Error\DomainError;

/**
 * Fábricas de erros esperados do módulo Notifications.
 */
final class NotificationErrors
{
    public static function notificationNotFound(): DomainError
    {
        return new DomainError(
            code: NotificationErrorCode::NotificationNotFound->value,
            message: 'Notificação não encontrada.',
            kind: NotificationErrorCode::NotificationNotFound->kind(),
            field: 'id',
        );
    }
}
