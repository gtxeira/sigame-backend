<?php

declare(strict_types=1);

namespace App\Notifications\Application\UseCase\MarkNotificationRead;

use App\Notifications\Application\Service\RealtimeNotificationEvent;
use App\Notifications\Application\Service\RealtimePublisherInterface;
use App\Notifications\Domain\Error\NotificationErrors;
use App\Notifications\Domain\Notification\Entity\Notification;
use App\Notifications\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Notifications\Domain\Notification\ValueObject\NotificationId;
use App\Notifications\Domain\Notification\ValueObject\RecipientId;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Instant;

final readonly class MarkNotificationReadHandler
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
        private RealtimePublisherInterface $realtimePublisher,
    ) {
    }

    /**
     * @return Result<Notification>
     */
    public function __invoke(NotificationId $id, RecipientId $recipientId): Result
    {
        $notification = $this->notificationRepository->transactional(function () use ($id, $recipientId): ?Notification {
            $notification = $this->notificationRepository->findForRecipient($id, $recipientId);

            if (null === $notification) {
                return null;
            }

            if ($notification->markRead(Instant::now())) {
                $this->notificationRepository->save($notification);
            }

            return $notification;
        });

        if (null === $notification) {
            return Result::failure([NotificationErrors::notificationNotFound()]);
        }

        $unread = $this->notificationRepository->countUnread($recipientId);

        $this->realtimePublisher->publish(
            $recipientId->value(),
            new RealtimeNotificationEvent(
                event: 'notification.read',
                data: [
                    'notificationId' => $notification->id()->value(),
                    'unreadCount' => $unread,
                ],
            ),
        );

        return Result::success($notification);
    }
}
