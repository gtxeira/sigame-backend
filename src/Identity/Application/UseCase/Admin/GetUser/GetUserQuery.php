<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Admin\GetUser;

use App\Identity\Domain\User\ValueObject\UserId;

final readonly class GetUserQuery
{
    public function __construct(
        public UserId $userId,
    ) {
    }
}
