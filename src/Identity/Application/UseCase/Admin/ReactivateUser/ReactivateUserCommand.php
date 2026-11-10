<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Admin\ReactivateUser;

use App\Identity\Domain\User\ValueObject\UserId;

final readonly class ReactivateUserCommand
{
    public function __construct(
        public UserId $userId,
        public UserId $actorId,
        public ?string $reason = null,
    ) {
    }
}
