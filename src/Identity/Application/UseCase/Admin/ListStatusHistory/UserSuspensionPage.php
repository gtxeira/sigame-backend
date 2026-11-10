<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Admin\ListStatusHistory;

use App\Identity\Domain\User\Entity\UserSuspension;

/**
 * Página do histórico de status de uma conta.
 */
final readonly class UserSuspensionPage
{
    /**
     * @param list<UserSuspension> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
