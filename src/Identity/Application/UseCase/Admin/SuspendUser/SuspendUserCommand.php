<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Admin\SuspendUser;

use App\Identity\Domain\User\ValueObject\UserId;

final readonly class SuspendUserCommand
{
    public function __construct(
        public UserId $userId,
        public UserId $actorId,
        public string $reason,
    ) {
    }
}
