<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;

/**
 * Plano de criação de uma notificação: conteúdo, público e canais.
 */
final readonly class NotificationPlan
{
    /**
     * @param list<string>                             $recipientIds
     * @param list<string>                             $excludeUserIds
     * @param list<NotificationChannel>                $channels
     * @param array<string, list<NotificationChannel>> $recipientChannels
     */
    public function __construct(
        public NotificationType $type,
        public string $deduplicationKey,
        public NotificationContent $content,
        public array $recipientIds,
        public array $excludeUserIds,
        public bool $includeInactive,
        public array $channels,
        public array $recipientChannels = [],
    ) {
    }

    /**
     * @return list<NotificationChannel>
     */
    public function channelsFor(string $recipientId): array
    {
        return $this->recipientChannels[$recipientId] ?? $this->channels;
    }
}
