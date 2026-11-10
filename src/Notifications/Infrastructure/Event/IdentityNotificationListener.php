<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Event;

use App\Identity\Domain\User\Event\UserReactivated;
use App\Identity\Domain\User\Event\UserSuspended;
use App\Notifications\Application\Service\NotificationIntent;
use App\Notifications\Application\Service\NotificationRequestPublisher;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Traduz eventos de conta em intenções de notificação.
 */
final readonly class IdentityNotificationListener
{
    public function __construct(
        private NotificationRequestPublisher $publisher,
    ) {
    }

    #[AsEventListener(event: 'identity.user.suspended')]
    public function onUserSuspended(UserSuspended $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::ACCOUNT_SUSPENDED,
            deduplicationKey: 'account-suspended:'.$event->suspensionId(),
            context: [
                'userId' => $event->userId(),
                'suspensionId' => $event->suspensionId(),
                'reason' => $event->reason(),
            ],
            actorId: $event->actorId(),
            includeInactive: true,
        ));
    }

    #[AsEventListener(event: 'identity.user.reactivated')]
    public function onUserReactivated(UserReactivated $event): void
    {
        $this->publisher->publish(new NotificationIntent(
            type: NotificationType::ACCOUNT_REACTIVATED,
            deduplicationKey: 'account-reactivated:'.$event->suspensionId(),
            context: [
                'userId' => $event->userId(),
                'suspensionId' => $event->suspensionId(),
                'reason' => $event->reason(),
            ],
            actorId: $event->actorId(),
        ));
    }
}
