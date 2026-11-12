<?php

declare(strict_types=1);

namespace App\Groups\Domain\Group\Repository;

use App\Groups\Domain\Group\ValueObject\GroupStatus;
use App\Shared\Domain\ValueObject\Instant;
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

    /**
     * `activityFrom`/`activityTo` descrevem a janela pedida; `activityGroupIds`
     * é o resultado resolvido pela porta compartilhada (`GroupActivityReaderInterface`)
     * antes da consulta, mantendo o módulo Groups sem importar Activities.
     *
     * @param list<string>|null $activityGroupIds
     */
    public function __construct(
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        public ?Modality $modality = null,
        public ?GroupStatus $status = null,
        public ?string $name = null,
        public bool $availableOnly = false,
        public ?Instant $activityFrom = null,
        public ?Instant $activityTo = null,
        public ?array $activityGroupIds = null,
    ) {
        $this->page = max(1, $page);
        $this->perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
    }

    /**
     * @param list<string>|null $activityGroupIds
     */
    public function withActivityGroupIds(?array $activityGroupIds): self
    {
        return new self(
            page: $this->page,
            perPage: $this->perPage,
            modality: $this->modality,
            status: $this->status,
            name: $this->name,
            availableOnly: $this->availableOnly,
            activityFrom: $this->activityFrom,
            activityTo: $this->activityTo,
            activityGroupIds: $activityGroupIds,
        );
    }
}
