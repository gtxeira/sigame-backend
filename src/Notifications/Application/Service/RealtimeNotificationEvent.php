<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

/**
 * Evento publicado no transporte em tempo real do sino.
 */
final readonly class RealtimeNotificationEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $event,
        public array $data,
    ) {
    }
}
