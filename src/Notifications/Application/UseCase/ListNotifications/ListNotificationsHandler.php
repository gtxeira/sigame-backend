<?php

declare(strict_types=1);

namespace App\Notifications\Application\UseCase\ListNotifications;

use App\Notifications\Domain\Notification\Repository\NotificationPage;
use App\Notifications\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Shared\Domain\Functional\Result;

final readonly class ListNotificationsHandler
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
    ) {
    }

    /**
     * @return Result<NotificationPage>
     */
    public function __invoke(ListNotificationsQuery $query): Result
    {
        return Result::success($this->notificationRepository->search($query->criteria));
    }
}
