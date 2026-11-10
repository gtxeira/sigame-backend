<?php

declare(strict_types=1);

namespace App\Notifications\Application\Message;

use App\Notifications\Domain\Notification\ValueObject\NotificationDeliveryId;

/**
 * Mensagem assíncrona de entrega em um canal externo.
 */
final readonly class DeliverNotificationMessage
{
    public function __construct(
        public NotificationDeliveryId $deliveryId,
    ) {
    }
}
