<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Admin;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Entity\UserSuspension;

/**
 * Resultado da suspensão/reativação de uma conta.
 */
final readonly class AccountStatusChangeResult
{
    public function __construct(
        public User $user,
        public ?UserSuspension $history,
        public bool $changed,
    ) {
    }
}
