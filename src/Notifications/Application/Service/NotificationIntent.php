<?php

declare(strict_types=1);

namespace App\Notifications\Application\Service;

use App\Notifications\Domain\Notification\ValueObject\NotificationType;

/**
 * Intenção de notificação publicada pelos listeners de domínio.
 *
 * O contexto carrega apenas dados primitivos/IDs; o handler resolve público e
 * conteúdo no worker.
 */
final readonly class NotificationIntent
{
    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $context
     */
    public function __construct(
        public NotificationType $type,
        public string $deduplicationKey,
        public array $context,
        public ?string $actorId = null,
        public bool $automatic = false,
        public bool $includeInactive = false,
    ) {
    }
}
