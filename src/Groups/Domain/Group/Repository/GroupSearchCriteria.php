<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Repository;

use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Shared\Domain\ValueObject\Modality;

/**
 * Filtros da listagem de grupos.
 */
final readonly class GroupSearchCriteria
{
    public const int DEFAULT_PER_PAGE = 20;
    public const int MAX_PER_PAGE = 30;

    public int $page;
    public int $perPage;

    public function __construct(
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        public ?Modality $modality = null,
        public ?GroupStatus $status = null,
        public ?string $name = null,
        public bool $availableOnly = false,
    ) {
        $this->page = max(1, $page);
        $this->perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
    }
}
