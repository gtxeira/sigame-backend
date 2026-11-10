<?php

declare(strict_types=1);

namespace App\Notifications\Application\Message;

use App\Notifications\Application\Service\NotificationIntent;

/**
 * Mensagem assíncrona que cria as notificações de um evento.
 */
final readonly class CreateNotificationsMessage
{
    public function __construct(
        public NotificationIntent $intent,
    ) {
    }
}
