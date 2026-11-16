<?php

declare(strict_types=1);

namespace App\Administration\Domain\Audit\Repository;

use App\Administration\Domain\Audit\Entity\AdminAuditEntry;

/**
 * Porta de persistência da trilha de auditoria.
 *
 * Só existem `append` e leitura: uma entrada nunca é alterada nem removida.
 */
interface AdminAuditRepositoryInterface
{
    public function append(AdminAuditEntry $entry): void;

    public function search(AdminAuditSearchCriteria $criteria): AdminAuditPage;
}
