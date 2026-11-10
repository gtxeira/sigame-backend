<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

/**
 * Conteúdo renderizado de uma notificação.
 */
final readonly class NotificationContent
{
    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $payload
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $payload,
    ) {
    }
}
