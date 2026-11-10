<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Repository;

use App\Identity\Domain\User\Entity\User;

/**
 * Página da listagem administrativa de usuários.
 */
final readonly class UserPage
{
    /**
     * @param list<User> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
