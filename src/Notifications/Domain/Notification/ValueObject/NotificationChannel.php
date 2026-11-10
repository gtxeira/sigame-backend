<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\ValueObject;

/**
 * Canais externos de entrega.
 *
 * O canal interno (sino) é o próprio registro de notificação e não precisa de
 * uma entrega separada.
 */
enum NotificationChannel: string
{
    case EMAIL = 'EMAIL';
    case WHATSAPP = 'WHATSAPP';
    case PUSH = 'PUSH';
}
