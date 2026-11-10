<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\Repository;

use App\Notifications\Domain\Notification\Entity\Notification;
use App\Notifications\Domain\Notification\ValueObject\DeduplicationKey;
use App\Notifications\Domain\Notification\ValueObject\NotificationId;
use App\Notifications\Domain\Notification\ValueObject\RecipientId;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Porta de persistência do inbox.
 */
interface NotificationRepositoryInterface
{
    public function save(Notification $notification): void;

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed;

    public function findForRecipient(NotificationId $id, RecipientId $recipientId): ?Notification;

    public function findByDeduplicationKey(
        RecipientId $recipientId,
        DeduplicationKey $key,
    ): ?Notification;

    public function search(NotificationSearchCriteria $criteria): NotificationPage;

    public function countUnread(RecipientId $recipientId): int;

    public function markAllRead(RecipientId $recipientId, Instant $now): int;
}
