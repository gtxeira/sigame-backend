<?php

declare(strict_types=1);

namespace App\Notifications\Application\Message;

use App\Notifications\Application\Service\NotificationChannelRegistry;
use App\Notifications\Domain\Notification\Repository\NotificationDeliveryRepositoryInterface;
use App\Notifications\Domain\Notification\ValueObject\DeliveryStatus;
use App\Shared\Application\User\UserContactReaderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Executa a entrega de uma notificação em um canal externo.
 */
#[AsMessageHandler]
final readonly class DeliverNotificationHandler
{
    public function __construct(
        private NotificationDeliveryRepositoryInterface $deliveryRepository,
        private NotificationChannelRegistry $channels,
        private UserContactReaderInterface $userContactReader,
    ) {
    }

    public function __invoke(DeliverNotificationMessage $message): void
    {
        $delivery = $this->deliveryRepository->findById($message->deliveryId);

        if (null === $delivery) {
            return;
        }

        if (DeliveryStatus::SENT === $delivery->status() || DeliveryStatus::SKIPPED === $delivery->status()) {
            return;
        }

        $recipient = $this->userContactReader->contact($delivery->notification()->recipientId()->value());

        if (null === $recipient) {
            $delivery->markSkipped('Destinatário não encontrado.');
            $this->deliveryRepository->save($delivery);

            return;
        }

        $channel = $this->channels->for($delivery->channel());

        if (null === $channel) {
            $delivery->markSkipped('Canal não configurado.');
            $this->deliveryRepository->save($delivery);

            return;
        }

        try {
            $channel->send($delivery->notification(), $recipient);
            $delivery->markSent(\App\Shared\Domain\ValueObject\Instant::now());
            $this->deliveryRepository->save($delivery);
        } catch (Throwable $exception) {
            $delivery->markFailed($exception->getMessage());
            $this->deliveryRepository->save($delivery);

            throw $exception;
        }
    }
}
