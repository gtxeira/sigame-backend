<?php

declare(strict_types=1);

namespace App\Administration\Domain\Audit\Repository;

use App\Administration\Domain\Audit\Entity\AdminAuditEntry;

/**
 * Página da trilha de auditoria.
 */
final readonly class AdminAuditPage
{
    /**
     * @param list<AdminAuditEntry> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
