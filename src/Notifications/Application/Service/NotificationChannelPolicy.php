<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Domain\Notification\ValueObject\NotificationChannel;
use App\Notifications\Domain\Notification\ValueObject\NotificationType;

/**
 * Política fixa de canais externos por tipo de notificação.
 *
 * O canal interno (sino) é sempre o registro persistido; os canais externos
 * são adicionais. `GROUP_OWNER_CHANGED` é a exceção por destinatário: só o
 * novo responsável recebe e-mail.
 *
 * Preferências por usuário ficam para uma versão futura.
 */
final readonly class NotificationChannelPolicy
{
    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     *
     * @return list<NotificationChannel>
     */
    public function channelsForRecipient(NotificationType $type, array $context, string $recipientId): array
    {
        if (NotificationType::GROUP_OWNER_CHANGED === $type
            && ($context['ownerId'] ?? null) === $recipientId) {
            return [NotificationChannel::EMAIL];
        }

        return $this->channelsFor($type);
    }

    /**
     * @return list<NotificationChannel>
     */
    public function channelsFor(NotificationType $type): array
    {
        return match ($type) {
            NotificationType::ACCOUNT_SUSPENDED,
            NotificationType::ACCOUNT_REACTIVATED,
            NotificationType::GROUP_ARCHIVED_OWNER => [NotificationChannel::EMAIL],
            default => [],
        };
    }
}
