<?php

declare(strict_types=1);

namespace App\Notifications\Application\UseCase\ListNotifications;

use App\Notifications\Domain\Notification\Repository\NotificationSearchCriteria;

final readonly class ListNotificationsQuery
{
    public function __construct(
        public NotificationSearchCriteria $criteria,
    ) {
    }
}
