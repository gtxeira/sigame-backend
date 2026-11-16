<?php

declare(strict_types=1);

namespace App\Administration\Domain\Audit\Repository;

use App\Shared\Domain\ValueObject\AdminAuditAction;
use App\Shared\Domain\ValueObject\AdminAuditTargetType;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Filtros da trilha de auditoria.
 */
final readonly class AdminAuditSearchCriteria
{
    public const int DEFAULT_PER_PAGE = 20;
    public const int MAX_PER_PAGE = 30;

    public int $page;
    public int $perPage;

    public function __construct(
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        public ?AdminAuditAction $action = null,
        public ?AdminAuditTargetType $targetType = null,
        public ?string $actorId = null,
        public ?Instant $from = null,
        public ?Instant $to = null,
    ) {
        $this->page = max(1, $page);
        $this->perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
    }
}
