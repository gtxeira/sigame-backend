<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Notification\Repository;

use App\Notifications\Domain\Notification\ValueObject\NotificationCategory;
use App\Notifications\Domain\Notification\ValueObject\NotificationReadFilter;
use App\Notifications\Domain\Notification\ValueObject\RecipientId;

/**
 * Filtros da listagem de notificações.
 */
final readonly class NotificationSearchCriteria
{
    public const int DEFAULT_PER_PAGE = 20;
    public const int MAX_PER_PAGE = 30;

    public int $page;
    public int $perPage;

    public function __construct(
        public RecipientId $recipientId,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        public NotificationReadFilter $read = NotificationReadFilter::ALL,
        public ?NotificationCategory $category = null,
    ) {
        $this->page = max(1, $page);
        $this->perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
    }
}
