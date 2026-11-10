<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\Repository;

use App\Notifications\Domain\Notification\Entity\Notification;

/**
 * Página de notificações.
 */
final readonly class NotificationPage
{
    /**
     * @param list<Notification> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
