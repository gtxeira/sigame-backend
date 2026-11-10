<?php

declare(strict_types=1);

namespace App\Notifications\Application\UseCase\GetUnreadCount;

use App\Notifications\Domain\Notification\Repository\NotificationRepositoryInterface;
use App\Notifications\Domain\Notification\ValueObject\RecipientId;
use App\Shared\Domain\Functional\Result;

final readonly class GetUnreadCountHandler
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
    ) {
    }

    /**
     * @return Result<int>
     */
    public function __invoke(RecipientId $recipientId): Result
    {
        return Result::success($this->notificationRepository->countUnread($recipientId));
    }
}
