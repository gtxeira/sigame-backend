<?php

declare(strict_types=1);

namespace App\Administration\Application\Audit\ListAdminAudit;

use App\Administration\Domain\Audit\Repository\AdminAuditPage;
use App\Administration\Domain\Audit\Repository\AdminAuditRepositoryInterface;
use App\Shared\Domain\Functional\Result;

/**
 * Lista a trilha de auditoria da administração, da mais recente para a mais antiga.
 */
final readonly class ListAdminAuditHandler
{
    public function __construct(
        private AdminAuditRepositoryInterface $repository,
    ) {
    }

    /**
     * @return Result<AdminAuditPage>
     */
    public function __invoke(ListAdminAuditQuery $query): Result
    {
        return Result::success($this->repository->search($query->criteria));
    }
}
