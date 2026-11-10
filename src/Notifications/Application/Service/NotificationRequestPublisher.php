<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Application\Message\CreateNotificationsMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Publica a criação de notificações sem derrubar a operação de negócio.
 */
final readonly class NotificationRequestPublisher
{
    public function __construct(
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function publish(NotificationIntent $intent): void
    {
        try {
            $this->bus->dispatch(new CreateNotificationsMessage($intent));
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao enfileirar notificação.', [
                'type' => $intent->type->value,
                'deduplicationKey' => $intent->deduplicationKey,
                'exception' => $exception,
            ]);
        }
    }
}
