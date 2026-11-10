<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;

/**
 * Política fixa de canais externos por tipo de notificação.
 *
 * O canal interno (sino) é sempre o registro persistido. Preferências por
 * usuário ficam para uma versão futura.
 */
final readonly class NotificationChannelPolicy
{
    /**
     * @return list<NotificationChannel>
     */
    public function channelsFor(NotificationType $type): array
    {
        return match ($type) {
            NotificationType::ACCOUNT_SUSPENDED,
            NotificationType::ACCOUNT_REACTIVATED => [NotificationChannel::EMAIL],
            default => [],
        };
    }
}
