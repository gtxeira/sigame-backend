<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Mercure;

use App\Notifications\Application\Service\RealtimeNotificationEvent;
use App\Notifications\Application\Service\RealtimePublisherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

/**
 * Publica eventos do sino em um tópico privado por usuário.
 *
 * A publicação é best-effort: uma falha no hub não desfaz a notificação.
 */
final readonly class MercureRealtimePublisher implements RealtimePublisherInterface
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        #[Autowire(env: 'MERCURE_TOPIC_BASE')]
        private string $topicBase,
    ) {
    }

    public function publish(string $userId, RealtimeNotificationEvent $event): void
    {
        try {
            $this->hub->publish(new Update(
                topics: $this->topicFor($userId),
                data: json_encode($event->data, JSON_THROW_ON_ERROR),
                private: true,
                type: $event->event,
            ));
        } catch (Throwable $exception) {
            $this->logger->error('Falha ao publicar atualização do sino no Mercure.', [
                'userId' => $userId,
                'event' => $event->event,
                'exception' => $exception,
            ]);
        }
    }

    public function topicFor(string $userId): string
    {
        return rtrim($this->topicBase, '/').'/'.$userId;
    }
}
