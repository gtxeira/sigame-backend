<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

/**
 * Porta de publicação em tempo real do canal interno.
 */
interface RealtimePublisherInterface
{
    public function publish(string $userId, RealtimeNotificationEvent $event): void;
}
