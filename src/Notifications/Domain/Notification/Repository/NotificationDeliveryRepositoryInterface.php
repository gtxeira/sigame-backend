<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\Repository;

use App\Notifications\Domain\Notification\Entity\NotificationDelivery;
use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use App\Notifications\Domain\Notification\ValueObject\NotificationDeliveryId;
use App\Notifications\Domain\Notification\ValueObject\NotificationId;

/**
 * Porta de persistência das entregas externas.
 */
interface NotificationDeliveryRepositoryInterface
{
    public function save(NotificationDelivery $delivery): void;

    public function findById(NotificationDeliveryId $id): ?NotificationDelivery;

    public function findForNotificationAndChannel(
        NotificationId $notificationId,
        NotificationChannel $channel,
    ): ?NotificationDelivery;
}
