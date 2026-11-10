<?php

declare(strict_types=1);

namespace App\Notifications\Application\Message;

use App\Notifications\Application\Service\NotificationPlan;
use App\Notifications\Application\Service\NotificationPlanner;
use App\Notifications\Application\Service\RealtimeNotificationEvent;
use App\Notifications\Application\Service\RealtimePublisherInterface;
use App\Notifications\Domain\Notification\Entity\Notification;
use App\Notifications\Domain\Notification\Entity\NotificationDelivery;
use App\Notifications\Domain\Notification\Repository\NotificationDeliveryRepositoryInterface;
use App\Notifications\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Notifications\Domain\Notification\ValueObject\DeduplicationKey;
use App\Notifications\Domain\Notification\ValueObject\NotificationBody;
use App\Notifications\Domain\Notification\ValueObject\NotificationPayload;
use App\Notifications\Domain\Notification\ValueObject\NotificationTitle;
use App\Notifications\Domain\Notification\ValueObject\RecipientId;
use App\Shared\Application\User\UserContactReaderInterface;
use App\Shared\Domain\ValueObject\Instant;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Cria as notificações de um evento, resolve público e canais e publica no
 * transporte em tempo real.
 */
#[AsMessageHandler]
final readonly class CreateNotificationsHandler
{
    public function __construct(
        private NotificationPlanner $planner,
        private NotificationRepositoryInterface $notificationRepository,
        private NotificationDeliveryRepositoryInterface $deliveryRepository,
        private UserContactReaderInterface $userContactReader,
        private RealtimePublisherInterface $realtimePublisher,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateNotificationsMessage $message): void
    {
        $plan = $this->planner->plan($message->intent);

        if (null === $plan) {
            return;
        }

        /** @var list<array{notification: Notification, deliveries: list<NotificationDelivery>}> $created */
        $created = $this->notificationRepository->transactional(
            fn (): array => $this->createForPlan($plan),
        );

        foreach ($created as $item) {
            $this->publishRealtime($item['notification']);

            foreach ($item['deliveries'] as $delivery) {
                try {
                    $this->bus->dispatch(new DeliverNotificationMessage($delivery->id()));
                } catch (Throwable $exception) {
                    $this->logger->error('Falha ao enfileirar entrega de notificação.', [
                        'deliveryId' => $delivery->id()->value(),
                        'exception' => $exception,
                    ]);
                }
            }
        }
    }

    /**
     * @return list<array{notification: Notification, deliveries: list<NotificationDelivery>}>
     */
    private function createForPlan(NotificationPlan $plan): array
    {
        $created = [];
        $title = NotificationTitle::fromString($plan->content->title);
        $body = NotificationBody::fromString($plan->content->body);
        $payload = NotificationPayload::fromArray($plan->content->payload);
        $key = DeduplicationKey::fromString($plan->deduplicationKey);
        $now = Instant::now();

        foreach (array_values(array_unique($plan->recipientIds)) as $recipientId) {
            if (in_array($recipientId, $plan->excludeUserIds, true)) {
                continue;
            }

            $contact = $this->userContactReader->contact($recipientId);

            if (null === $contact) {
                continue;
            }

            if (!$contact->active && !$plan->includeInactive) {
                continue;
            }

            $recipient = RecipientId::fromString($recipientId);
            $notification = $this->notificationRepository->findByDeduplicationKey($recipient, $key);

            if (null === $notification) {
                $notification = Notification::create(
                    recipientId: $recipient,
                    type: $plan->type,
                    title: $title,
                    body: $body,
                    payload: $payload,
                    deduplicationKey: $key,
                    createdAt: $now,
                );

                $this->notificationRepository->save($notification);
            }

            $deliveries = [];

            foreach ($plan->channels as $channel) {
                $existing = $this->deliveryRepository->findForNotificationAndChannel(
                    $notification->id(),
                    $channel,
                );

                if (null !== $existing) {
                    continue;
                }

                $delivery = NotificationDelivery::pending($notification, $channel, $now);
                $this->deliveryRepository->save($delivery);
                $deliveries[] = $delivery;
            }

            $created[] = ['notification' => $notification, 'deliveries' => $deliveries];
        }

        return $created;
    }

    private function publishRealtime(Notification $notification): void
    {
        try {
            $unread = $this->notificationRepository->countUnread($notification->recipientId());

            $this->realtimePublisher->publish(
                $notification->recipientId()->value(),
                new RealtimeNotificationEvent(
                    event: 'notification.created',
                    data: [
                        'notification' => [
                            'id' => $notification->id()->value(),
                            'type' => $notification->type()->value,
                            'category' => $notification->category()->value,
                            'title' => $notification->title(),
                            'body' => $notification->body(),
                            'data' => $notification->payload(),
                            'read' => false,
                            'readAt' => null,
                            'createdAt' => $notification->createdAt()->toIsoStringWithMilliseconds(),
                        ],
                        'unreadCount' => $unread,
                    ],
                ),
            );
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao publicar notificação em tempo real.', [
                'notificationId' => $notification->id()->value(),
                'exception' => $exception,
            ]);
        }
    }
}
