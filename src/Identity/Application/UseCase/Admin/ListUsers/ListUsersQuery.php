<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\Admin\ListUsers;

use App\Identity\Domain\User\Repository\UserSearchCriteria;

final readonly class ListUsersQuery
{
    public function __construct(
        public UserSearchCriteria $criteria,
    ) {
    }
}
