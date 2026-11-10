<?php

declare(strict_types=1);

namespace App\Identity\Domain\User\Repository;

use App\Identity\Domain\User\ValueObject\UserRole;
use App\Identity\Domain\User\ValueObject\UserStatus;

/**
 * Filtros da listagem administrativa de usuários.
 */
final readonly class UserSearchCriteria
{
    public const int DEFAULT_PER_PAGE = 20;
    public const int MAX_PER_PAGE = 30;

    public int $page;
    public int $perPage;

    public function __construct(
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        public ?string $query = null,
        public ?UserStatus $status = null,
        public ?UserRole $role = null,
    ) {
        $this->page = max(1, $page);
        $this->perPage = min(self::MAX_PER_PAGE, max(1, $perPage));
    }
}
