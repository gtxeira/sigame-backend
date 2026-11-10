<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Admin\ListStatusHistory;

use App\Identity\Domain\User\ValueObject\UserId;

final readonly class ListStatusHistoryQuery
{
    public function __construct(
        public UserId $userId,
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
