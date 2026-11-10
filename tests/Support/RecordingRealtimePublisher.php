<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Notifications\Application\Service\RealtimeNotificationEvent;
use App\Notifications\Application\Service\RealtimePublisherInterface;

/**
 * Publisher de teste que guarda os eventos em memória.
 */
final class RecordingRealtimePublisher implements RealtimePublisherInterface
{
    /**
     * @var list<array{userId: string, event: string, data: array<string, mixed>}>
     */
    private array $events = [];

    public function publish(string $userId, RealtimeNotificationEvent $event): void
    {
        $this->events[] = [
            'userId' => $userId,
            'event' => $event->event,
            'data' => $event->data,
        ];
    }

    /**
     * @return list<array{userId: string, event: string, data: array<string, mixed>}>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * @return array{userId: string, event: string, data: array<string, mixed>}|null
     */
    public function lastFor(string $userId): ?array
    {
        $last = null;

        foreach ($this->events as $event) {
            if ($event['userId'] === $userId) {
                $last = $event;
            }
        }

        return $last;
    }
}
