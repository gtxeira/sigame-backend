<?php

declare(strict_types=1);

namespace App\Notifications\Application\UseCase\MarkAllNotificationsRead;

use App\Notifications\Application\Service\RealtimeNotificationEvent;
use App\Notifications\Application\Service\RealtimePublisherInterface;
use App\Notifications\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Notifications\Domain\Notification\ValueObject\RecipientId;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;

final readonly class MarkAllNotificationsReadHandler
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
        private RealtimePublisherInterface $realtimePublisher,
    ) {
    }

    /**
     * @return Result<int>
     */
    public function __invoke(RecipientId $recipientId): Result
    {
        $count = $this->notificationRepository->transactional(
            fn (): int => $this->notificationRepository->markAllRead($recipientId, Instant::now()),
        );

        $this->realtimePublisher->publish(
            $recipientId->value(),
            new RealtimeNotificationEvent(
                event: 'notifications.read_all',
                data: ['unreadCount' => 0],
            ),
        );

        return Result::success($count);
    }
}
