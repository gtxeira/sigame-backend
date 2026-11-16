<?php

declare(strict_types=1);

namespace App\Administration\Application\Audit\ListAdminAudit;

use App\Administration\Domain\Audit\Repository\AdminAuditSearchCriteria;

final readonly class ListAdminAuditQuery
{
    public function __construct(
        public AdminAuditSearchCriteria $criteria,
    ) {
    }
}
