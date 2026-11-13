<?php

declare(strict_types=1);

namespace App\Activities\Domain\Activity\Repository;

use App\Activities\Domain\Activity\ValueObject\ActivityStatus;
use App\Shared\Domain\ValueObject\Instant;

/**
 * Filtros da listagem de atividades.
 */
final readonly class ActivitySearchCriteria
{
    public const int DEFAULT_PER_PAGE = 20;
    public const int MAX_PER_PAGE = 30;

    public int $page;
    public int $perPage;

    /**
     * @param list<string>|null $groupIds restringe a busca a estes grupos
     */
    public function __construct(
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        public ?ActivityStatus $status = null,
        public ?Instant $from = null,
        public ?Instant $to = null,
        public ?array $groupIds = null,
        /** Ordem por início: `asc` (padrão) ou `desc`. */
        public string $order = 'asc',
    ) {
        $this->page = max(1, $page);
        $this->perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
    }

    public function isDescending(): bool
    {
        return 'desc' === strtolower($this->order);
    }

    /**
     * @param list<string> $groupIds
     */
    public function withGroupIds(array $groupIds): self
    {
        return new self(
            page: $this->page,
            perPage: $this->perPage,
            status: $this->status,
            from: $this->from,
            to: $this->to,
            groupIds: $groupIds,
            order: $this->order,
        );
    }
}
