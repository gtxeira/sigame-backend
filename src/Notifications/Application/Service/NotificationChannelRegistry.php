<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Registro de adaptadores de canais externos.
 */
final readonly class NotificationChannelRegistry
{
    /**
     * @param iterable<NotificationChannelInterface> $channels
     */
    public function __construct(
        #[AutowireIterator('app.notification_channel')]
        private iterable $channels,
    ) {
    }

    public function for(NotificationChannel $channel): ?NotificationChannelInterface
    {
        foreach ($this->channels as $adapter) {
            if ($adapter->channel() === $channel) {
                return $adapter;
            }
        }

        return null;
    }
}
