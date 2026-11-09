<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Repository;

use App\Groups\Domain\Group\Entity\Group;

/**
 * Página de grupos retornada pela busca.
 */
final readonly class GroupPage
{
    /**
     * @param list<Group> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
